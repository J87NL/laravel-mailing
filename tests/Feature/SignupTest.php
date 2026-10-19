<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use J87NL\Mailing\Enums\ConsentAction;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Enums\SuppressionReason;
use J87NL\Mailing\Mail\ConfirmSubscriptionMail;
use J87NL\Mailing\Models\ConsentRecord;
use J87NL\Mailing\Models\Subscriber;
use J87NL\Mailing\Models\Subscription;
use J87NL\Mailing\Models\Suppression;
use J87NL\Mailing\Signup\SignupGuard;

beforeEach(function (): void {
    Mail::fake();

    $this->list = makeList();
});

function confirmationUrl(): string
{
    $url = null;

    Mail::assertQueued(ConfirmSubscriptionMail::class, function (ConfirmSubscriptionMail $mail) use (&$url): bool {
        $url = $mail->confirmUrl;

        return true;
    });

    return (string) $url;
}

test('the sign-up page shows a cookie-free form with a honeypot and a signed timestamp', function (): void {
    $response = $this->get('/mailing/nieuws/subscribe')
        ->assertOk()
        ->assertSee('Nieuwsbrief')
        ->assertSee(sprintf('action="%s"', route('mailing.subscribe.store', $this->list)), false)
        ->assertSee(sprintf('name="%s"', SignupGuard::HONEYPOT), false)
        ->assertSee(sprintf('name="%s"', SignupGuard::TIMESTAMP), false);

    expect($response->headers->getCookies())->toBeEmpty();
});

test('a sign-up is confirmed through the signed link in the confirmation mail', function (): void {
    $response = $this->post('/mailing/nieuws/subscribe', signupPayload($this->list))
        ->assertStatus(303)
        ->assertRedirect('/mailing/nieuws/subscribe?aangemeld=nieuws#mailing-nieuws');

    expect($response->headers->getCookies())->toBeEmpty();

    $subscription = Subscription::query()
        ->sole();

    expect($subscription->status)->toBe(SubscriptionStatus::Pending)
        ->and($subscription->subscriber->name)->toBeNull()
        ->and($subscription->requested_name)->toBe('Bezoeker');

    Mail::assertQueued(ConfirmSubscriptionMail::class, fn (ConfirmSubscriptionMail $mail): bool => $mail->hasTo('bezoeker@example.org')
        && $mail->hasFrom('info@rommelmarkt.test', 'Rommelmarkt'));

    $url = confirmationUrl();

    $this->get($url)
        ->assertOk()
        ->assertSee(__('mailing::confirm.confirm.button'))
        ->assertSee(sprintf('action="%s"', e($url)), false);

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Pending);

    $confirmed = $this->post($url)
        ->assertOk()
        ->assertSee(__('mailing::confirm.confirmed.title'));

    expect($confirmed->headers->getCookies())->toBeEmpty()
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Subscribed)
        ->and($subscription->fresh()->confirmed_at)->not->toBeNull()
        ->and($subscription->fresh()->subscriber->name)->toBe('Bezoeker')
        ->and(ConsentRecord::query()
            ->orderBy('id')->pluck('action')->all())->toBe([
                ConsentAction::Requested,
                ConsentAction::Confirmed,
            ]);
});

test('the confirmation link expires after seven days', function (): void {
    $this->post('/mailing/nieuws/subscribe', signupPayload($this->list));

    $url = confirmationUrl();

    $this->travel(8)->days();

    $this->post($url)
        ->assertForbidden()
        ->assertSee(__('mailing::confirm.expired.title'));

    expect(Subscription::query()
        ->sole()->status)->toBe(SubscriptionStatus::Pending);
});

test('a tampered confirmation link is refused', function (): void {
    $this->post('/mailing/nieuws/subscribe', signupPayload($this->list));

    $this->post(str_replace('signature=', 'signature=0', confirmationUrl()))->assertForbidden();

    expect(Subscription::query()
        ->sole()->status)->toBe(SubscriptionStatus::Pending);
});

test('the response is identical for new, pending and existing addresses', function (): void {
    makeSubscription($this->list, 'lid@example.org');

    $new = $this->post('/mailing/nieuws/subscribe', signupPayload($this->list, [
        'email' => 'nieuw@example.org',
    ]));
    $existing = $this->post('/mailing/nieuws/subscribe', signupPayload($this->list, [
        'email' => 'LID@example.org',
    ]));

    expect($existing->getStatusCode())->toBe($new->getStatusCode())
        ->and($existing->headers->get('Location'))->toBe($new->headers->get('Location'))
        ->and($existing->getContent())->toBe($new->getContent());

    Mail::assertQueued(ConfirmSubscriptionMail::class, 1);
    Mail::assertNotQueued(ConfirmSubscriptionMail::class, fn (ConfirmSubscriptionMail $mail): bool => $mail->hasTo('lid@example.org'));
});

test('a suppressed address gets the same response but no mail', function (): void {
    Suppression::query()
        ->create([
            'email' => 'weg@example.org',
            'reason' => SuppressionReason::UnsubscribeAll,
        ]);

    $this->post('/mailing/nieuws/subscribe', signupPayload($this->list, [
        'email' => 'weg@example.org',
    ]))->assertRedirect('/mailing/nieuws/subscribe?aangemeld=nieuws#mailing-nieuws');

    Mail::assertNothingQueued();

    expect(Subscriber::query()
        ->count())->toBe(0);
});

test('bots filling the honeypot or posting too fast get the success response without a sign-up', function (): void {
    $this->post('/mailing/nieuws/subscribe', signupPayload($this->list, [
        SignupGuard::HONEYPOT => 'https://spam.example',
    ]))->assertRedirect('/mailing/nieuws/subscribe?aangemeld=nieuws#mailing-nieuws');

    $this->post('/mailing/nieuws/subscribe', [
        'email' => 'snel@example.org',
        SignupGuard::TIMESTAMP => app(SignupGuard::class)->token($this->list->id),
    ])->assertRedirect('/mailing/nieuws/subscribe?aangemeld=nieuws#mailing-nieuws');

    $this->post('/mailing/nieuws/subscribe', signupPayload($this->list, [
        SignupGuard::TIMESTAMP => '1.forged',
    ]))->assertRedirect('/mailing/nieuws/subscribe?aangemeld=nieuws#mailing-nieuws');

    Mail::assertNothingQueued();

    expect(Subscriber::query()
        ->count())->toBe(0);
});

test('invalid input shows the errors without storing anything', function (): void {
    $this->post('/mailing/nieuws/subscribe', signupPayload($this->list, [
        'email' => 'geen-adres',
    ]))
        ->assertStatus(422)
        ->assertSee(__('mailing::signup.has_errors'))
        ->assertSee('value="geen-adres"', false);

    expect(Subscriber::query()
        ->count())->toBe(0);
});

test('sign-ups are rate limited per address and per IP address', function (): void {
    config()->set('mailing.signup.per_ip_per_hour', 5);

    foreach (range(1, 4) as $attempt) {
        $this->post('/mailing/nieuws/subscribe', signupPayload($this->list))
            ->assertRedirect('/mailing/nieuws/subscribe?aangemeld=nieuws#mailing-nieuws');
    }

    Mail::assertQueued(ConfirmSubscriptionMail::class, 3);

    $this->post('/mailing/nieuws/subscribe', signupPayload($this->list, [
        'email' => 'ander@example.org',
    ]))->assertRedirect();

    $this->post('/mailing/nieuws/subscribe', signupPayload($this->list, [
        'email' => 'derde@example.org',
    ]))
        ->assertStatus(429)
        ->assertHeader('Retry-After');

    expect(Subscriber::query()
        ->where('email', 'derde@example.org')->exists())->toBeFalse();
});

test('a list that is not public has no sign-up page', function (): void {
    $hidden = makeList([
        'key' => 'intern',
        'is_public' => false,
    ]);

    $this->get('/mailing/intern/subscribe')->assertNotFound();
    $this->post('/mailing/intern/subscribe', signupPayload($hidden))->assertNotFound();
});

test('a sign-up from a page returns to that page but never to another site', function (): void {
    $this->post('/mailing/nieuws/subscribe', signupPayload($this->list, [
        'return_to' => '/nieuwsbrief',
    ]))->assertRedirect('/nieuwsbrief?aangemeld=nieuws#mailing-nieuws');

    $this->post('/mailing/nieuws/subscribe', signupPayload($this->list, [
        'email' => 'ander@example.org',
        'return_to' => '//evil.example/pad',
    ]))->assertRedirect('/mailing/nieuws/subscribe?aangemeld=nieuws#mailing-nieuws');
});

test('an unsubscribed address can sign up again and confirm', function (): void {
    makeSubscription($this->list, 'terug@example.org', SubscriptionStatus::Unsubscribed);

    $this->post('/mailing/nieuws/subscribe', signupPayload($this->list, [
        'email' => 'terug@example.org',
    ]));

    $pending = Subscription::query()
        ->sole();

    expect($pending->status)->toBe(SubscriptionStatus::Pending)
        ->and($pending->confirmed_at)->toBeNull()
        ->and($pending->consent_at)->toBeNull();

    $this->post(confirmationUrl())->assertOk();

    expect(Subscription::query()
        ->sole()->status)->toBe(SubscriptionStatus::Subscribed);
});

test('an unconfirmed sign-up cannot change the name of a known address', function (): void {
    $subscriber = makeSubscription($this->list, 'lid@example.org', SubscriptionStatus::Unsubscribed)->subscriber;

    $this->post('/mailing/nieuws/subscribe', signupPayload($this->list, [
        'email' => 'lid@example.org',
        'name' => 'Iemand anders',
    ]));

    expect($subscriber->fresh()->name)->toBeNull();
});

test('the consent source names the page, limited in length', function (): void {
    $this->post('/mailing/nieuws/subscribe', signupPayload($this->list, [
        'return_to' => sprintf('/%s', str_repeat('a', 2000)),
    ]));

    $source = (string) Subscription::query()
        ->sole()->consent_source;

    expect($source)->toStartWith('signup: /aaa')
        ->and(mb_strlen($source))->toBeLessThanOrEqual(220);
});
