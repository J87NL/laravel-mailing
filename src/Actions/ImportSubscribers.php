<?php

declare(strict_types=1);

namespace J87NL\Mailing\Actions;

use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use J87NL\Mailing\Enums\ConsentAction;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Models\MailingImport;
use J87NL\Mailing\Models\MailingList;
use J87NL\Mailing\Models\Subscriber;
use J87NL\Mailing\Models\Subscription;
use J87NL\Mailing\Models\Suppression;
use J87NL\Mailing\Support\ConsentSource;
use J87NL\Mailing\Support\EmailAddress;
use SplFileObject;
use Throwable;

class ImportSubscribers
{
    private const int MAX_REPORTED_ERRORS = 50;

    private const int MAX_NAME_LENGTH = 120;

    private const array EMAIL_HEADERS = [
        'email',
        'e-mail',
        'emailadres',
        'e-mailadres',
        'mail',
    ];

    private const array NAME_HEADERS = [
        'name',
        'naam',
    ];

    public function __construct(
        private readonly RecordConsent $recordConsent,
        private readonly SendConfirmation $sendConfirmation,
    ) {}

    public function handle(
        MailingList $list,
        string $path,
        string $filename,
        string $consentSource,
        ?string $consentNote,
        ?int $actorId,
    ): MailingImport {
        $consentNote = trim((string) $consentNote);
        $hasDocumentedConsent = $consentNote !== '';
        $source = ConsentSource::make(ConsentSource::IMPORT, $consentSource);

        $import = MailingImport::query()
            ->create([
                'list_id' => $list->id,
                'filename' => $filename,
                'consent_source' => trim($consentSource),
                'consent_note' => $hasDocumentedConsent ? $consentNote : null,
                'created_by' => $actorId,
            ]);

        $errors = [];
        $seen = [];
        $perMinute = max(1, (int) config('mailing.import.confirmations_per_minute', 20));

        $rows = $this->rows($path);

        try {
            foreach ($rows as $line => $row) {
                $error = $this->importLine($import, $list, $line, $row, $seen, $source, $hasDocumentedConsent ? $consentNote : null, $actorId, $perMinute);

                if (! is_null($error)) {
                    $errors[] = $error;
                }
            }

            if ($rows->getReturn()) {
                array_unshift($errors, __('mailing::imports.errors.too_many_rows', [
                    'max' => (int) config('mailing.import.max_rows', 5000),
                ]));
            }
        } finally {
            $import->errors = $errors === [] ? null : array_slice($errors, 0, self::MAX_REPORTED_ERRORS);
            $import->save();
        }

        return $import;
    }

    /**
     * @param  array{email: string, name: string}  $row
     * @param  array<string, bool>  $seen
     */
    private function importLine(
        MailingImport $import,
        MailingList $list,
        int $line,
        array $row,
        array &$seen,
        string $source,
        ?string $consentNote,
        ?int $actorId,
        int $perMinute,
    ): ?string {
        $email = EmailAddress::normalize($row['email']);

        if ($email === '' && $row['name'] === '') {
            return null;
        }

        if (! $this->isValidEmail($email)) {
            $import->skipped++;

            return __('mailing::imports.errors.invalid_email', [
                'line' => $line,
            ]);
        }

        if (isset($seen[$email]) || Suppression::covers($email)) {
            $import->skipped++;

            return null;
        }

        $seen[$email] = true;

        try {
            $subscription = $this->importRow($list, $email, mb_substr($row['name'], 0, self::MAX_NAME_LENGTH), $source, $consentNote, $actorId);
        } catch (Throwable $e) {
            report($e);
            $import->skipped++;

            return __('mailing::imports.errors.row_failed', [
                'line' => $line,
            ]);
        }

        if (is_null($subscription)) {
            $import->skipped++;

            return null;
        }

        $import->imported++;

        if ($subscription->status !== SubscriptionStatus::Pending) {
            return null;
        }

        try {
            $this->sendConfirmation->handle($subscription, true, intdiv((int) $import->confirmations_sent, $perMinute));
        } catch (Throwable $e) {
            report($e);

            $subscription->forceFill([
                'confirmation_sent_at' => null,
            ])->save();

            return __('mailing::imports.errors.mail_failed', [
                'line' => $line,
            ]);
        }

        $import->confirmations_sent++;

        return null;
    }

    private function isValidEmail(string $email): bool
    {
        return Validator::make([
            'email' => $email,
        ], [
            'email' => [
                'required',
                'email:rfc',
                'max:254',
            ],
        ])->passes();
    }

    private function importRow(MailingList $list, string $email, string $name, string $source, ?string $consentNote, ?int $actorId): ?Subscription
    {
        return DB::transaction(function () use ($list, $email, $name, $source, $consentNote, $actorId): ?Subscription {
            $subscriber = Subscriber::query()
                ->firstOrCreate([
                    'email' => $email,
                ]);

            if ($name !== '' && (is_null($subscriber->name) || $subscriber->name === '')) {
                $subscriber->name = $name;
                $subscriber->save();
            }

            $subscription = Subscription::query()
                ->lockForUpdate()
                ->firstOrNew([
                    'subscriber_id' => $subscriber->id,
                    'list_id' => $list->id,
                ]);

            if ($subscription->exists) {
                return $this->claimStranded($subscription);
            }

            $confirmed = ! is_null($consentNote);

            $subscription->forceFill([
                'status' => $confirmed ? SubscriptionStatus::Subscribed : SubscriptionStatus::Pending,
                'consent_source' => $source,
                'consent_ip' => null,
                'consent_at' => $confirmed ? now() : null,
                'confirmation_sent_at' => $confirmed ? null : now(),
                'confirmed_at' => $confirmed ? now() : null,
            ])->save();

            $this->recordConsent->handle($subscriber, $list, ConsentAction::Imported, $source, null, $consentNote, $actorId);

            return $subscription;
        });
    }

    private function claimStranded(Subscription $subscription): ?Subscription
    {
        if ($subscription->status !== SubscriptionStatus::Pending || ! is_null($subscription->confirmation_sent_at)) {
            return null;
        }

        $subscription->forceFill([
            'confirmation_sent_at' => now(),
        ])->save();

        return $subscription;
    }

    /**
     * @return Generator<int, array{email: string, name: string}, mixed, bool>
     */
    private function rows(string $path): Generator
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);
        $file->setCsvControl($this->delimiter($path), '"', '');

        $maxRows = (int) config('mailing.import.max_rows', 5000);
        $emailColumn = 0;
        $nameColumn = 1;
        $line = 0;
        $dataRows = 0;

        foreach ($file as $row) {
            $line++;

            if (! is_array($row)) {
                continue;
            }

            $cells = array_map(fn (mixed $cell): string => trim((string) $cell), $row);

            if ($line === 1) {
                $cells[0] = trim((string) preg_replace('/^\x{FEFF}/u', '', $cells[0]), " \t\"");
                $headers = array_map('mb_strtolower', $cells);
                $emailHeader = array_values(array_intersect($headers, self::EMAIL_HEADERS))[0] ?? null;

                if (! is_null($emailHeader)) {
                    $emailColumn = (int) array_search($emailHeader, $headers, true);
                    $nameHeader = array_values(array_intersect($headers, self::NAME_HEADERS))[0] ?? null;
                    $nameColumn = is_null($nameHeader) ? -1 : (int) array_search($nameHeader, $headers, true);

                    continue;
                }
            }

            $dataRows++;

            if ($dataRows > $maxRows) {
                return true;
            }

            yield $line => [
                'email' => $cells[$emailColumn] ?? '',
                'name' => $nameColumn >= 0 ? ($cells[$nameColumn] ?? '') : '',
            ];
        }

        return false;
    }

    private function delimiter(string $path): string
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return ',';
        }

        $first = (string) fgets($handle);
        fclose($handle);

        return substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
    }
}
