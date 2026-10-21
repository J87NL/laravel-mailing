<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use J87NL\Mailing\Actions\ChangeCampaignStatus;
use J87NL\Mailing\Actions\SaveCampaign;
use J87NL\Mailing\Actions\StartCampaign;
use J87NL\Mailing\Attachments\CampaignAttachments;
use J87NL\Mailing\Attachments\MissingAttachmentFile;
use J87NL\Mailing\Contracts\DnsResolver;
use J87NL\Mailing\Enums\CampaignStatus;
use J87NL\Mailing\Enums\SendStatus;
use J87NL\Mailing\Mail\CampaignMail;
use J87NL\Mailing\MailingPermission;
use J87NL\Mailing\Models\Campaign;
use J87NL\Mailing\Models\CampaignAttachment;
use J87NL\Mailing\Models\Send;
use J87NL\Mailing\Preflight\PreflightCheck;
use J87NL\Mailing\Preflight\PreflightItem;
use J87NL\Mailing\Tests\Fakes\FakeDnsResolver;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

function realUpload(string $name, string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'mailing-upload');
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, null, null, true);
}

function attachFile(Campaign $campaign, string $name = 'flyer.pdf', int $kilobytes = 100, string $mime = 'application/pdf', bool $asLink = false): CampaignAttachment
{
    return app(CampaignAttachments::class)->store($campaign, UploadedFile::fake()->create($name, $kilobytes, $mime), $asLink);
}

/**
 * @return list<string>
 */
function attachedNames(Email $message): array
{
    return array_values(array_map(
        fn (DataPart $part): string => (string) $part->getFilename(),
        $message->getAttachments(),
    ));
}

function attachmentStatus(Campaign $campaign): ?PreflightItem
{
    return collect(app(PreflightCheck::class)->run($campaign->fresh()))
        ->first(fn (PreflightItem $item): bool => $item->key === 'attachments');
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function composerPayload(Campaign $campaign, array $overrides = []): array
{
    return [
        'list_id' => (string) $campaign->list_id,
        'subject' => $campaign->subject,
        'preheader' => (string) $campaign->preheader,
        'body_html' => $campaign->body_html,
        'body_text' => $campaign->body_text,
        ...$overrides,
    ];
}

beforeEach(function (): void {
    Storage::fake('local');

    Http::fake([
        '*' => Http::response('', 200),
    ]);

    $this->list = makeList();
    makeSubscription($this->list);
});

test('the composer stores attachments on the private disk', function (): void {
    $this->actingWithPermissions([MailingPermission::CampaignsEdit->value]);

    $this->post(route('mailing.campaigns.store'), [
        'list_id' => (string) $this->list->id,
        'subject' => 'Nieuws',
        'body_html' => '<p>De flyer voor volgend jaar staat in de bijlage.</p>',
        'body_text' => '',
        'attachments' => [
            UploadedFile::fake()->create('flyer.pdf', 120, 'application/pdf'),
            UploadedFile::fake()->image('plattegrond.png'),
        ],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $attachments = Campaign::query()
        ->sole()
        ->attachments;

    expect($attachments->pluck('name')->all())->toBe([
        'flyer.pdf',
        'plattegrond.png',
    ])
        ->and($attachments->pluck('as_link')->all())->toBe([
            false,
            false,
        ])
        ->and($attachments->first()->size)->toBe(120 * 1024)
        ->and($attachments->first()->path)->toStartWith('mailing/attachments/')
        ->and($attachments->first()->path)->not->toContain('flyer');

    foreach ($attachments as $attachment) {
        Storage::disk('local')->assertExists($attachment->path);
    }
});

test('attachments are refused when their type is not allowed', function (string $name, string $mime): void {
    $this->actingWithPermissions([MailingPermission::CampaignsEdit->value]);
    $campaign = makeCampaign($this->list);

    $this->put(route('mailing.campaigns.update', $campaign), composerPayload($campaign, [
        'attachments' => [
            UploadedFile::fake()->create($name, 10, $mime),
        ],
    ]))->assertSessionHasErrors('attachments.0');

    expect($campaign->attachments()->count())->toBe(0);
})->with([
    'a program' => ['setup.exe', 'application/x-msdownload'],
    'a web page' => ['pagina.html', 'text/html'],
    'a web page named as a pdf' => ['flyer.pdf', 'text/html'],
    'a pdf named as a web page' => ['flyer.html', 'application/pdf'],
    'a word file' => ['brief.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
]);

test('attachments are refused when they exceed the total size, counting the ones already there', function (): void {
    $this->actingWithPermissions([MailingPermission::CampaignsEdit->value]);
    $campaign = makeCampaign($this->list);
    $existing = attachFile($campaign, 'groot.pdf', 8 * 1024);

    $this->put(route('mailing.campaigns.update', $campaign), composerPayload($campaign, [
        'attachments' => [
            UploadedFile::fake()->create('nog-een.pdf', 3 * 1024, 'application/pdf'),
        ],
    ]))->assertSessionHasErrors('attachments');

    expect($campaign->attachments()->count())->toBe(1);

    $this->put(route('mailing.campaigns.update', $campaign), composerPayload($campaign, [
        'attachments' => [
            UploadedFile::fake()->create('nog-een.pdf', 3 * 1024, 'application/pdf'),
        ],
        'remove_attachments' => [
            (string) $existing->id,
        ],
    ]))->assertSessionHasNoErrors();

    expect($campaign->attachments()->pluck('name')->all())->toBe(['nog-een.pdf']);
    Storage::disk('local')->assertMissing($existing->path);
});

test('the composer switches attachments between a real attachment and a download link', function (): void {
    $this->actingWithPermissions([MailingPermission::CampaignsEdit->value]);
    $campaign = makeCampaign($this->list);
    $flyer = attachFile($campaign, 'flyer.pdf');
    $map = attachFile($campaign, 'kaart.pdf', 50, 'application/pdf', true);

    $this->put(route('mailing.campaigns.update', $campaign), composerPayload($campaign, [
        'attachment_links' => [
            (string) $flyer->id,
        ],
        'new_attachments_as_link' => '1',
        'attachments' => [
            UploadedFile::fake()->create('programma.pdf', 20, 'application/pdf'),
        ],
    ]))->assertSessionHasNoErrors();

    expect($campaign->attachments()->pluck('as_link', 'name')->all())->toBe([
        'flyer.pdf' => true,
        'kaart.pdf' => false,
        'programma.pdf' => true,
    ]);

    $this->put(route('mailing.campaigns.update', $campaign), composerPayload($campaign, [
        'attachment_links' => [
            (string) $map->id + 1000,
        ],
    ]))->assertSessionHasErrors('attachment_links');
});

test('the edit form lists the current attachments and ticks the ones sent as a link', function (): void {
    $campaign = makeCampaign($this->list);
    $flyer = attachFile($campaign, 'flyer.pdf');
    $map = attachFile($campaign, 'kaart.pdf', 50, 'application/pdf', true);

    $this->actingWithPermissions([MailingPermission::CampaignsEdit->value]);

    $form = $this->get(route('mailing.campaigns.edit', $campaign), [
        'X-Inertia' => 'true',
    ])->json('props.form');
    $fields = collect($form['fields'])->keyBy('name');

    expect($fields['attachments']['currentFiles'])->toBe([
        [
            'key' => (string) $flyer->id,
            'name' => 'flyer.pdf',
            'size' => 100 * 1024,
        ],
        [
            'key' => (string) $map->id,
            'name' => 'kaart.pdf',
            'size' => 50 * 1024,
        ],
    ])
        ->and($fields['attachments']['removeFlag'])->toBe('remove_attachments')
        ->and(collect($fields['attachment_links']['options'])->pluck('label')->all())->toBe([
            'flyer.pdf',
            'kaart.pdf',
        ])
        ->and($form['defaults']['attachment_links'])->toBe([(string) $map->id]);
});

test('campaign mails carry real attachments and download links for the linked files', function (): void {
    $campaign = makeCampaign($this->list);
    attachFile($campaign, 'flyer.pdf');
    $linked = attachFile($campaign, 'plattegrond.pdf', 3 * 1024, 'application/pdf', true);

    app(StartCampaign::class)->handle($campaign);
    runDispatcher();

    $message = sentMessages()->sole()->getOriginalMessage();

    expect($message)->toBeInstanceOf(Email::class)
        ->and(attachedNames($message))->toBe(['flyer.pdf'])
        ->and($message->getHtmlBody())->toContain('plattegrond.pdf')
        ->and($message->getHtmlBody())->toContain(sprintf('/mailing/attachments/%s?expires=', $linked->token))
        ->and($message->getTextBody())->toContain(sprintf('/mailing/attachments/%s?expires=', $linked->token));
});

test('test mails carry the same attachments and download links', function (): void {
    $campaign = makeCampaign($this->list);
    attachFile($campaign, 'flyer.pdf');
    $linked = attachFile($campaign, 'plattegrond.pdf', 50, 'application/pdf', true);

    $this->actingWithPermissions([
        MailingPermission::CampaignsEdit->value,
        MailingPermission::CampaignsSend->value,
    ]);

    $this->post(route('mailing.campaigns.test', $campaign), [
        'recipients' => 'test@example.org',
    ])->assertSessionHasNoErrors();

    $message = sentMessages()->sole()->getOriginalMessage();

    expect($message)->toBeInstanceOf(Email::class)
        ->and(attachedNames($message))->toBe(['flyer.pdf'])
        ->and($message->getTextBody())->toContain(sprintf('/mailing/attachments/%s?expires=', $linked->token));
});

test('a download link is signed, expires and sets no cookie', function (): void {
    config()->set('mailing.attachments.link_valid_days', 30);

    $campaign = makeCampaign($this->list);
    $attachment = attachFile($campaign, 'plattegrond.pdf', 50, 'application/pdf', true);
    $attachments = app(CampaignAttachments::class);
    $url = $attachments->downloadUrl($attachment, $attachments->linkExpiry());

    $response = $this->get($url);

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertDownload('plattegrond.pdf');

    expect($response->headers->getCookies())->toBe([])
        ->and($response->headers->has('Set-Cookie'))->toBeFalse();

    $this->get(str_replace($attachment->token, str_repeat('a', 40), $url))->assertForbidden();
    $this->get(route('mailing.attachments.download', [
        'token' => $attachment->token,
    ]))->assertForbidden();

    $this->travel(31)->days();

    $this->get($url)->assertForbidden();
});

test('a download link of a deleted attachment is gone', function (): void {
    $campaign = makeCampaign($this->list);
    $attachment = attachFile($campaign, 'plattegrond.pdf', 50, 'application/pdf', true);
    $attachments = app(CampaignAttachments::class);
    $url = $attachments->downloadUrl($attachment, $attachments->linkExpiry());

    $campaign->delete();

    $this->get($url)->assertNotFound();
    Storage::disk('local')->assertMissing($attachment->path);
});

test('attachments need the permission to edit campaigns', function (): void {
    $campaign = makeCampaign($this->list);

    $this->actingWithPermissions([MailingPermission::Lists->value]);

    $this->put(route('mailing.campaigns.update', $campaign), composerPayload($campaign, [
        'attachments' => [
            UploadedFile::fake()->create('flyer.pdf', 10, 'application/pdf'),
        ],
    ]))->assertForbidden();

    expect($campaign->attachments()->count())->toBe(0);
});

test('attachments can no longer be changed once the campaign is scheduled', function (CampaignStatus $status): void {
    $campaign = makeCampaign($this->list);
    $attachment = attachFile($campaign);
    $campaign->forceFill([
        'status' => $status,
    ])->save();

    $this->actingWithPermissions([MailingPermission::CampaignsEdit->value]);

    $this->get(route('mailing.campaigns.edit', $campaign))->assertRedirect(route('mailing.campaigns.show', $campaign));

    $this->put(route('mailing.campaigns.update', $campaign), composerPayload($campaign, [
        'attachments' => [
            UploadedFile::fake()->create('later.pdf', 10, 'application/pdf'),
        ],
        'remove_attachments' => [
            (string) $attachment->id,
        ],
    ]))->assertSessionHasErrors('campaign');

    expect($campaign->attachments()->pluck('name')->all())->toBe(['flyer.pdf'])
        ->and(Storage::disk('local')->allFiles())->toBe([$attachment->path]);
})->with([
    'scheduled' => [CampaignStatus::Scheduled],
    'sending' => [CampaignStatus::Sending],
    'paused' => [CampaignStatus::Paused],
    'sent' => [CampaignStatus::Sent],
    'cancelled' => [CampaignStatus::Cancelled],
]);

test('the pre-flight check warns about large attachments and mails that are mostly attachment', function (): void {
    $dns = app(DnsResolver::class);

    expect($dns)->toBeInstanceOf(FakeDnsResolver::class);

    $campaign = makeCampaign($this->list, [
        'body_html' => sprintf('<p>%s</p>', str_repeat('Alles over de rommelmarkt van dit jaar. ', 10)),
    ]);

    expect(attachmentStatus($campaign))->toBeNull();

    $flyer = attachFile($campaign, 'flyer.pdf', 500);

    expect(attachmentStatus($campaign)?->status)->toBe(PreflightItem::OK);

    attachFile($campaign, 'foto.jpg', 2 * 1024, 'image/jpeg');

    expect(attachmentStatus($campaign)?->status)->toBe(PreflightItem::WARNING)
        ->and(attachmentStatus($campaign)?->message)->toBe(__('mailing::preflight.messages.attachments.large', [
            'size' => '2.5 MB',
            'max' => '2 MB',
        ]));

    $campaign->attachments()
        ->where('name', 'foto.jpg')
        ->update([
            'as_link' => true,
        ]);

    expect(attachmentStatus($campaign)?->status)->toBe(PreflightItem::OK);

    $campaign->update([
        'body_html' => '<p>Zie de bijlage.</p>',
    ]);

    expect(attachmentStatus($campaign)?->status)->toBe(PreflightItem::WARNING)
        ->and(attachmentStatus($campaign)?->message)->toBe(__('mailing::preflight.messages.attachments.mostly_attachment', [
            'min' => 200,
        ]));

    $flyer->update([
        'as_link' => true,
    ]);

    expect(attachmentStatus($campaign)?->status)->toBe(PreflightItem::OK)
        ->and(PreflightCheck::blocks(array_filter(
            app(PreflightCheck::class)->run($campaign->fresh()),
            fn (PreflightItem $item): bool => $item->key === 'attachments',
        )))->toBeFalse();
});

test('a changed attachment refreshes the cached pre-flight check', function (): void {
    $campaign = makeCampaign($this->list);
    $preflight = app(PreflightCheck::class);

    $this->travel(-1)->minutes();
    $campaign->touch();
    $this->travelBack();

    expect(collect($preflight->cached($campaign->fresh()))->pluck('key')->all())->not->toContain('attachments');

    attachFile($campaign);

    expect(collect($preflight->cached($campaign->fresh()))->pluck('key')->all())->toContain('attachments');
});

test('the campaign page lists the attachments and how they are sent', function (): void {
    $campaign = makeCampaign($this->list);
    $flyer = attachFile($campaign, 'flyer.pdf');
    $map = attachFile($campaign, 'kaart.pdf', 50, 'application/pdf', true);

    $this->actingWithPermissions([MailingPermission::CampaignsEdit->value]);

    $attachments = $this->get(route('mailing.campaigns.show', $campaign), [
        'X-Inertia' => 'true',
    ])->json('props.attachments');

    expect($attachments)->toBe([
        [
            'id' => $flyer->id,
            'name' => 'flyer.pdf',
            'size' => '100 KB',
            'delivery' => __('mailing::campaigns.attachments.as_attachment'),
        ],
        [
            'id' => $map->id,
            'name' => 'kaart.pdf',
            'size' => '50 KB',
            'delivery' => __('mailing::campaigns.attachments.as_link'),
        ],
    ]);
});

test('attachments are deleted with the campaign and pruned after the retention period', function (): void {
    config()->set('mailing.attachments.link_valid_days', 30);
    config()->set('mailing.attachments.retention_days', 30);

    $draft = makeCampaign($this->list);
    $draftFile = attachFile($draft);

    $this->actingWithPermissions([MailingPermission::CampaignsEdit->value]);
    $this->delete(route('mailing.campaigns.destroy', $draft))->assertRedirect();

    Storage::disk('local')->assertMissing($draftFile->path);

    $old = makeCampaign($this->list);
    $oldFile = attachFile($old);
    $old->forceFill([
        'status' => CampaignStatus::Sent,
        'finished_at' => now()->subDays(31),
    ])->save();

    $recent = makeCampaign($this->list);
    $recentFile = attachFile($recent);
    $recent->forceFill([
        'status' => CampaignStatus::Cancelled,
        'finished_at' => now()->subDays(29),
    ])->save();

    $sending = makeCampaign($this->list);
    $sendingFile = attachFile($sending);
    $sending->forceFill([
        'status' => CampaignStatus::Sending,
    ])->save();

    $this->artisan('mailing:prune')->assertSuccessful();

    expect(CampaignAttachment::query()
        ->orderBy('id')
        ->pluck('id')
        ->all())->toBe([
            $recentFile->id,
            $sendingFile->id,
        ]);

    Storage::disk('local')->assertMissing($oldFile->path);
    Storage::disk('local')->assertExists($recentFile->path);
    Storage::disk('local')->assertExists($sendingFile->path);
});

test('download links never outlive the files behind them', function (): void {
    config()->set('mailing.attachments.link_valid_days', 60);
    config()->set('mailing.attachments.retention_days', 30);

    $campaign = makeCampaign($this->list);
    $file = attachFile($campaign);
    $campaign->forceFill([
        'status' => CampaignStatus::Sent,
        'finished_at' => now()->subDays(45),
    ])->save();

    $this->artisan('mailing:prune')->assertSuccessful();

    Storage::disk('local')->assertExists($file->path);
});

test('the type is read from the content, not from the name', function (): void {
    $this->actingWithPermissions([MailingPermission::CampaignsEdit->value]);
    $campaign = makeCampaign($this->list);

    $this->put(route('mailing.campaigns.update', $campaign), composerPayload($campaign, [
        'attachments' => [
            realUpload('flyer.pdf', '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>'),
        ],
    ]))->assertSessionHasErrors('attachments.0');

    expect($campaign->attachments()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);

    $png = UploadedFile::fake()->image('plattegrond.png');

    $this->put(route('mailing.campaigns.update', $campaign), composerPayload($campaign, [
        'attachments' => [
            realUpload('plattegrond.pdf', (string) file_get_contents($png->getRealPath())),
        ],
    ]))->assertSessionHasNoErrors();

    $attachment = $campaign->attachments()
        ->sole();

    expect($attachment->name)->toBe('plattegrond.png')
        ->and($attachment->mime_type)->toBe('image/png')
        ->and($attachment->path)->toEndWith('.png');
});

test('a new campaign with attachments needs the permission to edit campaigns', function (): void {
    $this->actingWithPermissions([MailingPermission::Lists->value]);

    $this->post(route('mailing.campaigns.store'), [
        'list_id' => (string) $this->list->id,
        'subject' => 'Nieuws',
        'body_html' => '<p>De flyer staat in de bijlage.</p>',
        'attachments' => [
            UploadedFile::fake()->create('flyer.pdf', 10, 'application/pdf'),
        ],
    ])->assertForbidden();

    expect(Campaign::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('a save that finds the campaign no longer a draft under the lock stores no files', function (): void {
    $campaign = makeCampaign($this->list);
    $stale = $campaign->fresh();

    Campaign::query()
        ->whereKey($campaign->id)
        ->update([
            'status' => CampaignStatus::Scheduled,
        ]);

    $saved = app(SaveCampaign::class)->handle($stale, composerPayload($campaign, [
        'attachments' => [
            UploadedFile::fake()->create('flyer.pdf', 10, 'application/pdf'),
        ],
    ]), 1);

    expect($saved)->toBeFalse()
        ->and(CampaignAttachment::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('the total size is checked again under the lock, so two saves at once cannot pass the limit', function (): void {
    $campaign = makeCampaign($this->list);
    $existing = attachFile($campaign, 'groot.pdf', 8 * 1024);

    expect(fn () => app(SaveCampaign::class)->handle($campaign->fresh(), composerPayload($campaign, [
        'attachments' => [
            UploadedFile::fake()->create('nog-een.pdf', 3 * 1024, 'application/pdf'),
        ],
    ]), 1))->toThrow(ValidationException::class);

    expect($campaign->attachments()->pluck('name')->all())->toBe(['groot.pdf'])
        ->and(Storage::disk('local')->allFiles())->toBe([$existing->path]);
});

test('a download link sets no cookie even when the host puts the public routes in the web group', function (): void {
    config()->set('mailing.routes.public_middleware', ['web']);

    require __DIR__.'/../../routes/public.php';

    Route::getRoutes()->refreshNameLookups();

    $this->get(route('mailing.unsubscribe.show', [
        'token' => 'onbekend',
    ]))->assertCookie((string) config('session.cookie'));

    $campaign = makeCampaign($this->list);
    $attachment = attachFile($campaign, 'plattegrond.pdf', 50, 'application/pdf', true);
    $attachments = app(CampaignAttachments::class);

    $response = $this->get($attachments->downloadUrl($attachment, $attachments->linkExpiry()));

    $response->assertOk()->assertDownload('plattegrond.pdf');

    expect($response->headers->getCookies())->toBe([]);
});

test('download links are limited to 60 requests a minute', function (): void {
    $campaign = makeCampaign($this->list);
    $attachment = attachFile($campaign, 'plattegrond.pdf', 1, 'application/pdf', true);
    $attachments = app(CampaignAttachments::class);
    $url = $attachments->downloadUrl($attachment, $attachments->linkExpiry());

    for ($i = 0; $i < 60; $i++) {
        $this->get($url)->assertOk();
    }

    $this->get($url)->assertTooManyRequests();
});

test('a missing attachment file blocks scheduling and test sends', function (): void {
    $campaign = makeCampaign($this->list);
    $attachment = attachFile($campaign, 'flyer.pdf');

    Storage::disk('local')->delete($attachment->path);

    $items = collect(app(PreflightCheck::class)->run($campaign->fresh()))->keyBy('key');

    expect($items['attachments']->status)->toBe(PreflightItem::ERROR)
        ->and($items['attachments']->message)->toContain('flyer.pdf')
        ->and($items['spam_score']->status)->toBe(PreflightItem::WARNING);

    $this->actingWithPermissions([
        MailingPermission::CampaignsEdit->value,
        MailingPermission::CampaignsSend->value,
    ]);

    $this->get(route('mailing.campaigns.show', $campaign), [
        'X-Inertia' => 'true',
    ])->assertOk();
    $this->post(route('mailing.campaigns.schedule', $campaign))->assertSessionHasErrors('campaign');

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Draft)
        ->and(fn () => (new CampaignMail($campaign->fresh()))->attachments())->toThrow(MissingAttachmentFile::class, $attachment->path);

    $this->post(route('mailing.campaigns.test', $campaign), [
        'recipients' => 'test@example.org',
    ])->assertSessionHasErrors([
        'recipients' => __('mailing::preflight.messages.attachments.missing', [
            'names' => 'flyer.pdf',
        ]),
    ]);

    expect(sentMessages())->toHaveCount(0)
        ->and(RateLimiter::attempts('mailing-test-send:1'))->toBe(0);
});

test('a file that goes missing while sending pauses the campaign instead of failing every send', function (): void {
    makeSubscription($this->list, 'twee@example.org');
    $campaign = makeCampaign($this->list);
    $attachment = attachFile($campaign, 'flyer.pdf');
    $content = (string) Storage::disk('local')->get($attachment->path);
    app(StartCampaign::class)->handle($campaign);

    Storage::disk('local')->delete($attachment->path);

    runDispatcher();

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Paused)
        ->and(sentMessages())->toHaveCount(0)
        ->and(Send::query()->where('status', SendStatus::Failed)->count())->toBe(0)
        ->and(Send::query()->where('status', SendStatus::Queued)->count())->toBe(2)
        ->and(Send::query()->whereNotNull('last_response')->value('last_response'))->toContain($attachment->path);

    Storage::disk('local')->put($attachment->path, $content);
    app(ChangeCampaignStatus::class)->resume($campaign);
    $this->travel(2)->minutes();
    runDispatcher();

    expect(sentMessages())->toHaveCount(2)
        ->and(attachedNames(sentMessages()->first()->getOriginalMessage()))->toBe(['flyer.pdf']);
});

test('attachments are not pruned when a retry started the campaign again in the meantime', function (): void {
    config()->set('mailing.attachments.retention_days', 30);

    $campaign = makeCampaign($this->list);
    $attachment = attachFile($campaign);
    $campaign->forceFill([
        'status' => CampaignStatus::Sent,
        'finished_at' => now()->subDays(31),
    ])->save();

    $retried = false;

    Campaign::retrieved(function (Campaign $retrieved) use (&$retried): void {
        if ($retried) {
            return;
        }

        $retried = true;

        Campaign::query()
            ->whereKey($retrieved->id)
            ->update([
                'status' => CampaignStatus::Sending,
                'finished_at' => null,
            ]);
    });

    $this->artisan('mailing:prune')->assertSuccessful();

    expect($retried)->toBeTrue()
        ->and($campaign->attachments()->count())->toBe(1)
        ->and($campaign->fresh()->attachments_pruned_at)->toBeNull();

    Storage::disk('local')->assertExists($attachment->path);
});

test('attachments of a finished campaign with sends still waiting are not pruned', function (): void {
    config()->set('mailing.attachments.retention_days', 30);

    $campaign = makeCampaign($this->list);
    attachFile($campaign);
    app(StartCampaign::class)->handle($campaign);
    $campaign->forceFill([
        'status' => CampaignStatus::Sent,
        'finished_at' => now()->subDays(31),
    ])->save();

    $this->artisan('mailing:prune')->assertSuccessful();

    expect($campaign->attachments()->count())->toBe(1);
});

test('downloads have their own rate limit, apart from other public routes', function (): void {
    $campaign = makeCampaign($this->list);
    $attachment = attachFile($campaign, 'plattegrond.pdf', 1, 'application/pdf', true);
    $attachments = app(CampaignAttachments::class);
    $url = $attachments->downloadUrl($attachment, $attachments->linkExpiry());

    for ($i = 0; $i < 60; $i++) {
        $this->get($url)->assertOk();
    }

    $this->get(route('mailing.confirm.script'))->assertOk();
});

test('failed sends are not retried once the attachments are pruned', function (): void {
    config()->set('mailing.attachments.retention_days', 30);

    $campaign = makeCampaign($this->list);
    attachFile($campaign);
    app(StartCampaign::class)->handle($campaign);

    Send::query()
        ->update([
            'status' => SendStatus::Failed,
        ]);
    $campaign->forceFill([
        'status' => CampaignStatus::Sent,
        'finished_at' => now()->subDays(31),
    ])->save();
    $updatedAt = $campaign->fresh()->updated_at;

    $this->travel(1)->minutes();
    $this->artisan('mailing:prune')->assertSuccessful();

    $campaign->refresh();

    expect($campaign->attachments_pruned_at)->not->toBeNull()
        ->and($campaign->updated_at?->equalTo($updatedAt))->toBeTrue()
        ->and(app(ChangeCampaignStatus::class)->retryFailed($campaign))->toBe(0)
        ->and(Send::query()->sole()->status)->toBe(SendStatus::Failed);

    $this->actingWithPermissions([
        MailingPermission::CampaignsEdit->value,
        MailingPermission::CampaignsSend->value,
    ]);

    $this->post(route('mailing.campaigns.retry', $campaign))->assertSessionHasErrors('campaign');

    $page = $this->get(route('mailing.campaigns.show', $campaign), [
        'X-Inertia' => 'true',
    ]);
    $retry = collect($page->json('props.actions'))->firstWhere('url', route('mailing.campaigns.retry', $campaign));

    expect($retry['disabledReason'])->toBe(__('mailing::campaigns.errors.attachments_pruned'))
        ->and($page->json('props.campaign.attachmentsPrunedAt'))->not->toBeNull()
        ->and(Send::query()->sole()->status)->toBe(SendStatus::Failed);
});

test('both attachment warnings show when both apply', function (): void {
    $campaign = makeCampaign($this->list, [
        'body_html' => '<p>Zie de bijlage.</p>',
    ]);
    attachFile($campaign, 'foto.jpg', 3 * 1024, 'image/jpeg');

    $item = attachmentStatus($campaign);

    expect($item?->status)->toBe(PreflightItem::WARNING)
        ->and($item?->message)->toContain(__('mailing::preflight.messages.attachments.large', [
            'size' => '3 MB',
            'max' => '2 MB',
        ]))
        ->and($item?->message)->toContain(__('mailing::preflight.messages.attachments.mostly_attachment', [
            'min' => 200,
        ]));
});

test('a stored file is deleted again when its database row cannot be written', function (): void {
    $campaign = makeCampaign($this->list);

    CampaignAttachment::creating(function (): void {
        throw new RuntimeException('database down');
    });

    expect(fn () => attachFile($campaign))->toThrow(RuntimeException::class, 'database down')
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});
