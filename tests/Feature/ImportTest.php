<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use J87NL\Mailing\Enums\ConsentAction;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Enums\SuppressionReason;
use J87NL\Mailing\Mail\ConfirmSubscriptionMail;
use J87NL\Mailing\MailingPermission;
use J87NL\Mailing\Models\ConsentRecord;
use J87NL\Mailing\Models\MailingImport;
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
