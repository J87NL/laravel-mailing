<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use J87NL\Mailing\Actions\SendConfirmation;
use J87NL\Mailing\Enums\ConsentAction;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Enums\SuppressionReason;
use J87NL\Mailing\Mail\ConfirmSubscriptionMail;
use J87NL\Mailing\MailingPermission;
use J87NL\Mailing\Models\ConsentRecord;
use J87NL\Mailing\Models\MailingImport;
use J87NL\Mailing\Models\Subscriber;
use J87NL\Mailing\Models\Subscription;
use J87NL\Mailing\Models\Suppression;

beforeEach(function (): void {
    Mail::fake();

    $this->list = makeList();

    $this->actingWithPermissions([MailingPermission::Subscribers->value]);
});

function csvUpload(string $contents): UploadedFile
{
    return UploadedFile::fake()->createWithContent('adressen.csv', $contents);
}

test('an import needs a consent source', function (): void {
    $this->post(route('mailing.imports.store'), [
        'list_id' => (string) $this->list->id,
        'file' => csvUpload("email\njan@example.org\n"),
    ])->assertSessionHasErrors('consent_source');

    expect(Subscription::query()
        ->count())->toBe(0);
});

test('imported addresses start pending and get one confirmation mail', function (): void {
    makeSubscription($this->list, 'al@example.org');
    Suppression::query()
        ->create([
            'email' => 'weg@example.org',
            'reason' => SuppressionReason::HardBounce,
        ]);

    $this->post(route('mailing.imports.store'), [
        'list_id' => (string) $this->list->id,
        'file' => csvUpload("email;name\nJan@Example.org;Jan\npiet@example.org;\njan@example.org;Dubbel\nal@example.org;Al\nweg@example.org;Weg\ngeen-adres;Fout\n"),
        'consent_source' => 'Intekenlijst markt mei',
    ])->assertRedirect(route('mailing.imports.index'))->assertSessionHasErrors('import');

    $pending = Subscription::query()
        ->with('subscriber')
        ->where('status', SubscriptionStatus::Pending)
        ->get();

    expect($pending->pluck('subscriber.email')->sort()->values()->all())->toBe([
        'jan@example.org',
        'piet@example.org',
    ])
        ->and($pending->firstWhere('subscriber.email', 'jan@example.org')?->subscriber->name)->toBe('Jan')
        ->and($pending->first()?->consent_source)->toBe('import: Intekenlijst markt mei');

    Mail::assertQueued(ConfirmSubscriptionMail::class, 2);

    $import = MailingImport::query()
        ->sole();

    expect($import)
        ->imported->toBe(2)
        ->skipped->toBe(4)
        ->confirmations_sent->toBe(2)
        ->created_by->toBe(1)
        ->and($import->errors)->toBe([
            __('mailing::imports.errors.invalid_email', ['line' => 7]),
        ]);
});

test('addresses with documented earlier consent are subscribed without a confirmation mail', function (): void {
    $this->post(route('mailing.imports.store'), [
        'list_id' => (string) $this->list->id,
        'file' => csvUpload("jan@example.org,Jan\npiet@example.org,Piet\n"),
        'consent_source' => 'Oude ledenlijst',
        'consent_note' => 'Aanmeldformulieren 2024 in de map bij de penningmeester',
    ])->assertRedirect(route('mailing.imports.index'))->assertSessionHasNoErrors();

    expect(Subscription::query()
        ->where('status', SubscriptionStatus::Subscribed)->count())->toBe(2);

    Mail::assertNothingQueued();

    $record = ConsentRecord::query()
        ->first();

    expect($record?->action)->toBe(ConsentAction::Imported)
        ->and($record?->note)->toBe('Aanmeldformulieren 2024 in de map bij de penningmeester')
        ->and($record?->actor_id)->toBe(1);
});

test('an import never re-subscribes an address that unsubscribed, even with documented consent', function (): void {
    $unsubscribed = makeSubscription($this->list, 'weg@example.org', SubscriptionStatus::Unsubscribed);

    $this->post(route('mailing.imports.store'), [
        'list_id' => (string) $this->list->id,
        'file' => csvUpload("weg@example.org,Weg\n"),
        'consent_source' => 'Oude ledenlijst',
        'consent_note' => 'Map bij de penningmeester',
    ])->assertSessionHasNoErrors();

    expect($unsubscribed->fresh()->status)->toBe(SubscriptionStatus::Unsubscribed)
        ->and(MailingImport::query()
            ->sole()->skipped)->toBe(1);

    Mail::assertNothingQueued();
});

test('an import reads Dutch headers behind a byte order mark and reports rows over the limit', function (): void {
    config()->set('mailing.import.max_rows', 2);

    $this->post(route('mailing.imports.store'), [
        'list_id' => (string) $this->list->id,
        'file' => csvUpload("\u{FEFF}Naam;E-mailadres\nJan;jan@example.org\nPiet;piet@example.org\nKees;kees@example.org\n"),
        'consent_source' => 'Intekenlijst markt mei',
    ])->assertSessionHasErrors([
        'import' => __('mailing::imports.errors.too_many_rows', ['max' => 2]),
    ]);

    $subscriptions = Subscription::query()
        ->with('subscriber')
        ->get();

    expect($subscriptions->pluck('subscriber.email')->sort()->values()->all())->toBe([
        'jan@example.org',
        'piet@example.org',
    ])
        ->and($subscriptions->firstWhere('subscriber.email', 'jan@example.org')?->subscriber->name)->toBe('Jan');
});

test('import confirmations use their own wording and are spread over time', function (): void {
    config()->set('mailing.import.confirmations_per_minute', 2);

    $this->post(route('mailing.imports.store'), [
        'list_id' => (string) $this->list->id,
        'file' => csvUpload("email\na@example.org\nb@example.org\nc@example.org\n"),
        'consent_source' => 'Intekenlijst markt mei',
    ])->assertRedirect(route('mailing.imports.index'));

    $mails = Mail::queued(ConfirmSubscriptionMail::class);

    expect($mails)->toHaveCount(3)
        ->and($mails->every(fn (ConfirmSubscriptionMail $mail): bool => $mail->imported))->toBeTrue()
        ->and($mails->filter(fn (ConfirmSubscriptionMail $mail): bool => is_null($mail->delay))->count())->toBe(2)
        ->and($mails->first()?->envelope()->subject)->toBe(__('mailing::mail.confirm_import.subject', [
            'list' => 'Nieuwsbrief',
        ]));
});

test('an import keeps going when a confirmation mail fails and a re-import sends it after all', function (): void {
    $this->app->bind(SendConfirmation::class, fn (): SendConfirmation => new class extends SendConfirmation
    {
        public function handle(Subscription $subscription, bool $imported = false, int $delayMinutes = 0): void
        {
            if ($subscription->subscriber->email === 'kapot@example.org') {
                throw new RuntimeException('Queue unavailable');
            }

            parent::handle($subscription, $imported, $delayMinutes);
        }
    });

    $csv = sprintf("email,name\na@example.org,%s\nkapot@example.org,K\nb@example.org,B\n", str_repeat('x', 300));

    $this->post(route('mailing.imports.store'), [
        'list_id' => (string) $this->list->id,
        'file' => csvUpload($csv),
        'consent_source' => 'Intekenlijst markt mei',
    ])->assertRedirect(route('mailing.imports.index'));

    Mail::assertQueued(ConfirmSubscriptionMail::class, 2);

    $import = MailingImport::query()
        ->sole();
    $stranded = Subscription::query()
        ->whereHas('subscriber', fn (Builder $query): Builder => $query->where('email', 'kapot@example.org'))
        ->sole();

    expect($import)
        ->imported->toBe(3)
        ->confirmations_sent->toBe(2)
        ->and($import->errors)->toBe([
            __('mailing::imports.errors.mail_failed', ['line' => 3]),
        ])
        ->and($stranded->status)->toBe(SubscriptionStatus::Pending)
        ->and($stranded->confirmation_sent_at)->toBeNull()
        ->and(mb_strlen((string) Subscriber::query()
            ->where('email', 'a@example.org')
            ->value('name')))->toBe(120);

    $this->app->forgetInstance(SendConfirmation::class);
    $this->app->offsetUnset(SendConfirmation::class);

    $this->post(route('mailing.imports.store'), [
        'list_id' => (string) $this->list->id,
        'file' => csvUpload($csv),
        'consent_source' => 'Intekenlijst markt mei',
    ])->assertRedirect(route('mailing.imports.index'));

    Mail::assertQueued(ConfirmSubscriptionMail::class, fn (ConfirmSubscriptionMail $mail): bool => $mail->hasTo('kapot@example.org'));
    Mail::assertQueued(ConfirmSubscriptionMail::class, 3);
});
