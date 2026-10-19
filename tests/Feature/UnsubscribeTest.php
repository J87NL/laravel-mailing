<?php

declare(strict_types=1);

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Mail;
use J87NL\Mailing\Enums\ConsentAction;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Enums\SuppressionReason;
use J87NL\Mailing\Mail\ConfirmSubscriptionMail;
use J87NL\Mailing\Mail\ListMailable;
use J87NL\Mailing\Models\ConsentRecord;
use J87NL\Mailing\Models\Suppression;

beforeEach(function (): void {
    $this->list = makeList();
    $this->subscription = makeSubscription($this->list);
});

test('a GET on the unsubscribe link never unsubscribes', function (): void {
    $response = $this->get(sprintf('/mailing/unsubscribe/%s', $this->subscription->unsubscribe_token))
        ->assertOk()
        ->assertSee(__('mailing::unsubscribe.choose.this_list', ['list' => 'Nieuwsbrief']))
        ->assertSee(__('mailing::unsubscribe.choose.all_lists'));

    expect($response->headers->getCookies())->toBeEmpty()
        ->and($this->subscription->fresh()->status)->toBe(SubscriptionStatus::Subscribed);
});

test('a one-click POST as sent by mail clients unsubscribes immediately without a CSRF token or cookie', function (): void {
    $response = $this->call('POST', sprintf('/mailing/unsubscribe/%s', $this->subscription->unsubscribe_token), [
        'List-Unsubscribe' => 'One-Click',
    ]);

    $response->assertOk()->assertSee(__('mailing::unsubscribe.done.title'));

    expect($response->headers->getCookies())->toBeEmpty()
        ->and($this->subscription->fresh()->status)->toBe(SubscriptionStatus::Unsubscribed)
        ->and($this->subscription->fresh()->unsubscribed_at)->not->toBeNull()
        ->and(ConsentRecord::query()->sole()->action)->toBe(ConsentAction::Unsubscribed)
        ->and(Suppression::query()->count())->toBe(0);
});

test('unsubscribing from all lists adds a suppression that blocks a new sign-up', function (): void {
    Mail::fake();

    $other = makeList([
        'key' => 'vrijwilligers',
        'name' => 'Vrijwilligers',
    ]);
    $otherSubscription = makeSubscription($other);

    $this->post(sprintf('/mailing/unsubscribe/%s', $this->subscription->unsubscribe_token), [
        'scope' => 'all',
    ])
        ->assertOk()
        ->assertSee(__('mailing::unsubscribe.done.all'));

    expect($this->subscription->fresh()->status)->toBe(SubscriptionStatus::Unsubscribed)
        ->and($otherSubscription->fresh()->status)->toBe(SubscriptionStatus::Unsubscribed)
        ->and(Suppression::query()->sole()->reason)->toBe(SuppressionReason::UnsubscribeAll);

    $this->post('/mailing/nieuws/subscribe', signupPayload($this->list, [
        'email' => 'lid@example.org',
    ]))->assertRedirect();

    Mail::assertNotQueued(ConfirmSubscriptionMail::class);
});

test('an unknown unsubscribe token is not found', function (): void {
    $this->get('/mailing/unsubscribe/onbekend')->assertNotFound();
    $this->post('/mailing/unsubscribe/onbekend', [
        'List-Unsubscribe' => 'One-Click',
    ])->assertNotFound();
});

test('list mails carry the List-Unsubscribe, List-Unsubscribe-Post and List-Id headers', function (): void {
    config()->set('mailing.unsubscribe.mailto', 'afmelden@rommelmarkt.test');

    $mail = new class($this->subscription) extends ListMailable
    {
        public function envelope(): Envelope
        {
            return new Envelope(
                from: 'info@rommelmarkt.test',
                subject: 'Nieuws',
            );
        }

        public function content(): Content
        {
            return new Content(htmlString: '<p>Nieuws</p>');
        }
    };

    $headers = Mail::mailer('array')->to('lid@example.org')->send($mail)?->getOriginalMessage()->getHeaders();

    $url = route('mailing.unsubscribe.show', [
        'token' => $this->subscription->unsubscribe_token,
    ]);

    expect($headers?->get('List-Unsubscribe')?->getBodyAsString())->toBe(sprintf('<%s>, <mailto:afmelden@rommelmarkt.test?subject=unsubscribe%%20%s>', $url, $this->subscription->unsubscribe_token))
        ->and($headers?->get('List-Unsubscribe-Post')?->getBodyAsString())->toBe('List-Unsubscribe=One-Click')
        ->and($headers?->get('List-Id')?->getBodyAsString())->toBe('"Nieuwsbrief" <nieuws.rommelmarkt.test>');
});
