<?php

declare(strict_types=1);

namespace J87NL\Mailing\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use J87NL\Mailing\Enums\ConsentAction;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Models\MailingImport;
use J87NL\Mailing\Models\MailingList;
use J87NL\Mailing\Models\Subscriber;
use J87NL\Mailing\Models\Subscription;
use J87NL\Mailing\Models\Suppression;
use J87NL\Mailing\Support\EmailAddress;
use SplFileObject;

class ImportSubscribers
{
    private const int MAX_REPORTED_ERRORS = 50;

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
        $source = sprintf('import: %s', trim($consentSource));

        $import = MailingImport::query()->create([
            'list_id' => $list->id,
            'filename' => $filename,
            'consent_source' => trim($consentSource),
            'consent_note' => $hasDocumentedConsent ? $consentNote : null,
            'created_by' => $actorId,
        ]);

        $errors = [];
        $toConfirm = [];
        $seen = [];

        foreach ($this->rows($path) as $line => $row) {
            $email = EmailAddress::normalize($row['email']);

            if ($email === '' && $row['name'] === '') {
                continue;
            }

            if (Validator::make(['email' => $email], ['email' => ['required', 'email:rfc', 'max:254']])->fails()) {
                $errors[] = __('mailing::imports.errors.invalid_email', [
                    'line' => $line,
                ]);
                $import->skipped++;

                continue;
            }

            if (isset($seen[$email]) || Suppression::covers($email)) {
                $import->skipped++;

                continue;
            }

            $seen[$email] = true;

            $subscription = $this->importRow($list, $email, $row['name'], $source, $hasDocumentedConsent ? $consentNote : null, $actorId);

            if (is_null($subscription)) {
                $import->skipped++;

                continue;
            }

            $import->imported++;

            if ($subscription->status === SubscriptionStatus::Pending) {
                $toConfirm[] = $subscription;
            }
        }

        foreach ($toConfirm as $subscription) {
            $this->sendConfirmation->handle($subscription);
            $import->confirmations_sent++;
        }

        $import->errors = $errors === [] ? null : array_slice($errors, 0, self::MAX_REPORTED_ERRORS);
        $import->save();

        return $import;
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
                ->firstOrNew([
                    'subscriber_id' => $subscriber->id,
                    'list_id' => $list->id,
                ]);

            if ($subscription->exists && $subscription->status !== SubscriptionStatus::Unsubscribed) {
                return null;
            }

            if ($subscription->exists && is_null($consentNote)) {
                return null;
            }

            $confirmed = ! is_null($consentNote);

            $subscription->forceFill([
                'status' => $confirmed ? SubscriptionStatus::Subscribed : SubscriptionStatus::Pending,
                'consent_source' => $source,
                'consent_ip' => null,
                'consent_at' => $confirmed ? now() : null,
                'confirmed_at' => $confirmed ? now() : null,
                'unsubscribed_at' => null,
            ])->save();

            $this->recordConsent->handle($subscriber, $list, ConsentAction::Imported, $source, null, $consentNote, $actorId);

            return $subscription;
        });
    }

    /**
     * @return iterable<int, array{email: string, name: string}>
     */
    private function rows(string $path): iterable
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);
        $file->setCsvControl($this->delimiter($path), '"', '');

        $maxRows = (int) config('mailing.import.max_rows', 5000);
        $emailColumn = 0;
        $nameColumn = 1;
        $line = 0;

        foreach ($file as $row) {
            $line++;

            if (! is_array($row) || $line > $maxRows + 1) {
                continue;
            }

            $cells = array_map(fn (mixed $cell): string => trim((string) $cell, " \t\n\r\0\x0B\u{FEFF}"), $row);

            if ($line === 1 && in_array('email', array_map('mb_strtolower', $cells), true)) {
                $lowered = array_map('mb_strtolower', $cells);
                $emailColumn = (int) array_search('email', $lowered, true);
                $found = array_search('name', $lowered, true);
                $nameColumn = $found === false ? -1 : (int) $found;

                continue;
            }

            yield $line => [
                'email' => $cells[$emailColumn] ?? '',
                'name' => $nameColumn >= 0 ? ($cells[$nameColumn] ?? '') : '',
            ];
        }
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
