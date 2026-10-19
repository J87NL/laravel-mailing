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

    expect(Subscription::query()->count())->toBe(0);
});

test('imported addresses start pending and get one confirmation mail', function (): void {
    makeSubscription($this->list, 'al@example.org');
    Suppression::query()->create([
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

    $import = MailingImport::query()->sole();

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

    expect(Subscription::query()->where('status', SubscriptionStatus::Subscribed)->count())->toBe(2);

    Mail::assertNothingQueued();

    $record = ConsentRecord::query()->first();

    expect($record?->action)->toBe(ConsentAction::Imported)
        ->and($record?->note)->toBe('Aanmeldformulieren 2024 in de map bij de penningmeester')
        ->and($record?->actor_id)->toBe(1);
});
