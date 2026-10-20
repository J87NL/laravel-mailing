<?php

declare(strict_types=1);

use Illuminate\Support\Facades\URL;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Http\Controllers\Public\ConfirmationScriptController;

beforeEach(function (): void {
    $this->subscription = makeSubscription(makeList(), 'nieuw@example.org', SubscriptionStatus::Pending);
    $this->url = URL::temporarySignedRoute('mailing.confirm.show', now()->addDay(), [
        'subscription' => $this->subscription->id,
    ]);
});

function confirmationPageScriptSources(string $policy): string
{
    foreach (explode(';', $policy) as $directive) {
        if (str_starts_with(trim($directive), 'script-src')) {
            return trim($directive);
        }
    }

    return '';
}

test('opening the confirmation link never confirms, but the page submits itself with a same-origin script', function (): void {
    $response = $this->get($this->url)
        ->assertOk()
        ->assertSee(sprintf('<script src="%s" defer></script>', route('mailing.confirm.script')), false)
        ->assertSee(ConfirmationScriptController::FORM_ATTRIBUTE, false)
        ->assertSee(__('mailing::confirm.confirm.button'));

    $policy = (string) $response->headers->get('Content-Security-Policy');

    expect($this->subscription->fresh()->status)->toBe(SubscriptionStatus::Pending)
        ->and(confirmationPageScriptSources($policy))->toBe("script-src 'self'")
        ->and($policy)->toContain("object-src 'none'")
        ->and($policy)->toContain("frame-ancestors 'none'")
        ->and($response->headers->getCookies())->toBeEmpty();
});

test('the auto-submit script is a same-origin file that only submits the marked form', function (): void {
    $response = $this->get(route('mailing.confirm.script'))->assertOk();

    expect($response->headers->get('Content-Type'))->toStartWith('text/javascript')
        ->and((string) $response->getContent())->toContain(sprintf('form[%s]', ConfirmationScriptController::FORM_ATTRIBUTE))
        ->and((string) $response->getContent())->toContain('form.submit()');
});

test('the POST that the script sends confirms and shows the subscribed state without a script', function (): void {
    $response = $this->post($this->url)
        ->assertOk()
        ->assertSee(__('mailing::confirm.confirmed.title'))
        ->assertDontSee(route('mailing.confirm.script'));

    expect($this->subscription->fresh()->status)->toBe(SubscriptionStatus::Subscribed)
        ->and(confirmationPageScriptSources((string) $response->headers->get('Content-Security-Policy')))->toBe("script-src 'none'");
});

test('other public mailing pages stay script-free', function (): void {
    $signUp = $this->get(route('mailing.subscribe.show', $this->subscription->mailingList));
    $unsubscribe = $this->get(route('mailing.unsubscribe.show', [
        'token' => $this->subscription->unsubscribe_token,
    ]));

    expect(confirmationPageScriptSources((string) $signUp->headers->get('Content-Security-Policy')))->toBe("script-src 'none'")
        ->and(confirmationPageScriptSources((string) $unsubscribe->headers->get('Content-Security-Policy')))->toBe("script-src 'none'");
});

test('the confirmation page keeps the host\'s other script sources and only swaps none for self', function (): void {
    config()->set('cms.security_headers.csp', [
        'script-src' => [
            'https://stats.example.org',
        ],
    ]);

    $policy = (string) $this->get($this->url)->headers->get('Content-Security-Policy');

    expect(confirmationPageScriptSources($policy))->toBe("script-src 'self' https://stats.example.org");
});

test('without a policy from the host the confirmation page gets none either', function (): void {
    config()->set('cms.security_headers.enabled', false);

    $this->get($this->url)
        ->assertOk()
        ->assertHeaderMissing('Content-Security-Policy');
});

test('expired and already confirmed links never load the script', function (): void {
    $this->get(str_replace('signature=', 'signature=0', $this->url))
        ->assertForbidden()
        ->assertDontSee(route('mailing.confirm.script'));

    $this->post($this->url)->assertOk();

    $this->get($this->url)
        ->assertOk()
        ->assertSee(__('mailing::confirm.confirmed.title'))
        ->assertDontSee(route('mailing.confirm.script'));

    $this->post($this->url)
        ->assertOk()
        ->assertSee(__('mailing::confirm.confirmed.title'));
});
