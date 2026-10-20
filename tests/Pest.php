<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Models\Campaign;
use J87NL\Mailing\Models\MailingList;
use J87NL\Mailing\Models\Subscriber;
use J87NL\Mailing\Models\Subscription;
use J87NL\Mailing\Sending\Dispatcher;
use J87NL\Mailing\Signup\SignupGuard;
use J87NL\Mailing\Tests\TestCase;
use Symfony\Component\Mailer\SentMessage;

pest()->extend(TestCase::class)->in('Feature');

/**
 * @param  array<string, mixed>  $attributes
 */
function makeList(array $attributes = []): MailingList
{
    return MailingList::query()
        ->create([
            'key' => 'nieuws',
            'name' => 'Nieuwsbrief',
            'mailer' => 'array',
            'sender_identity' => 'rommelmarkt',
            'is_public' => true,
            ...$attributes,
        ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function signupPayload(MailingList $list, array $overrides = []): array
{
    test()->travel(-10)->seconds();
    $token = app(SignupGuard::class)->token($list->id);
    test()->travelBack();

    return [
        'email' => 'bezoeker@example.org',
        'name' => 'Bezoeker',
        SignupGuard::TIMESTAMP => $token,
        ...$overrides,
    ];
}

function makeSubscription(MailingList $list, string $email = 'lid@example.org', SubscriptionStatus $status = SubscriptionStatus::Subscribed): Subscription
{
    $subscriber = Subscriber::query()
        ->firstOrCreate([
            'email' => $email,
        ]);

    return Subscription::query()
        ->create([
            'subscriber_id' => $subscriber->id,
            'list_id' => $list->id,
            'status' => $status,
            'confirmed_at' => $status === SubscriptionStatus::Subscribed ? now() : null,
        ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function makeCampaign(MailingList $list, array $attributes = []): Campaign
{
    return Campaign::query()
        ->create([
            'list_id' => $list->id,
            'subject' => 'Nieuws van de rommelmarkt',
            'preheader' => 'De data voor volgend jaar',
            'body_html' => '<h2>Volgende markt</h2><p>Op zaterdag 3 april staan we weer klaar. Kijk op <a href="https://rommelmarkt.test/data">de site</a>.</p>',
            'body_text' => 'Op zaterdag 3 april staan we weer klaar.',
            ...$attributes,
        ]);
}

/**
 * @return Collection<int, SentMessage>
 */
function sentMessages(): Collection
{
    $transport = Mail::mailer('array')->getSymfonyTransport();

    return $transport instanceof ArrayTransport ? $transport->messages() : collect();
}

/**
 * @return array{started: int, dispatched: int, interrupted: int, finished: int}
 */
function runDispatcher(?CarbonImmutable $now = null): array
{
    return app(Dispatcher::class)->run($now ?? CarbonImmutable::now());
}
