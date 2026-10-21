# Testing

## The package test suite

The package repository is tested with Pest on Orchestra Testbench and an in-memory SQLite database:

```bash
composer update
vendor/bin/pest
```

Run the suite from a clone of the package repository. It installs `j87nl/laravel-cms` as a dev dependency to test the sign-up block. The tests
replace the outside world through the contracts, with fakes in `tests/Fakes`: a mailbox reader, a
DNS resolver, a spam scorer and several failing mail transports (SMTP refusals, rejected recipients and API
errors). The Lettermint payloads in
[`tests/fixtures/lettermint`](https://github.com/J87NL/laravel-mailing/tree/main/tests/fixtures/lettermint)
are written by hand, see [Lettermint](delivery/lettermint.md#unverified-payloads). So are the
Postfix log lines in `tests/fixtures/postfix` and `tests/Feature/PostfixLogTest.php`, after the
format Postfix documents; the Postfix log tests feed lines through a fake `PostfixLogReader` or
through temporary files.

The `tests` directory is only in the package repository; it is not part of the installed package.

## Testing your application

### Keep tests offline

Bind offline implementations of the contracts in your test environment, for example in the
`setUp()` of your base test case. `FakeDnsResolver` stands for a `DnsResolver` of your own:

```php
use J87NL\Mailing\Preflight\NullSpamScorer;

config([
    'mailing.preflight.spam_scorer' => NullSpamScorer::class,
    'mailing.preflight.dns_resolver' => App\Testing\FakeDnsResolver::class,
]);
```

The default `NullMailboxReader` already reads nothing. The link check of the pre-flight sends real
HTTP requests through Laravel's HTTP client, so `Http::fake()` or `Http::preventStrayRequests()`
applies to it.

The contracts are bound as singletons when they are first resolved. Set the config before that, or
bind an instance directly:

```php
use J87NL\Mailing\Contracts\SpamScorer;
use J87NL\Mailing\Preflight\NullSpamScorer;

$this->app->instance(SpamScorer::class, new NullSpamScorer);
```

### Configure a sender

Lists need a sender identity:

```php
config([
    'mailing.senders' => [
        'test' => [
            'email' => 'news@example.org',
            'name' => 'Example',
        ],
    ],
]);
```

### Posting the sign-up form

A sign-up needs a valid form token that is at least `mailing.signup.min_seconds` old. Create the
token in the past:

```php
use J87NL\Mailing\Signup\SignupGuard;

$this->travel(-10)->seconds();
$token = app(SignupGuard::class)->token($list->id);
$this->travelBack();

$this->post(route('mailing.subscribe.store', $list), [
    'email' => 'visitor@example.org',
    SignupGuard::TIMESTAMP => $token,
])->assertRedirect();
```

`app(SignupGuard::class)->token($list->id, resubmission: true)` gives the token of a form shown
again after a `422`; it may be posted at once, without travelling in time.

The confirmation mail is a queued mailable, so `Mail::fake()` and `Mail::assertQueued()` see it:

```php
use J87NL\Mailing\Mail\ConfirmSubscriptionMail;

Mail::assertQueued(ConfirmSubscriptionMail::class);
```

### Running the dispatcher

`J87NL\Mailing\Sending\Dispatcher::run()` takes the current time, which makes sending testable
without the scheduler:

```php
use Carbon\CarbonImmutable;
use J87NL\Mailing\Sending\Dispatcher;

$result = app(Dispatcher::class)->run(CarbonImmutable::now());

// ['started' => 1, 'dispatched' => 20, 'interrupted' => 0, 'finished' => 0]
```

With the `sync` queue the send jobs run during `run()`.

### Permissions

The admin routes check abilities with `can`. In tests, define them with `Gate::define()`:

```php
use Illuminate\Support\Facades\Gate;
use J87NL\Mailing\MailingPermission;

foreach (MailingPermission::names() as $permission) {
    Gate::define($permission, fn (): bool => true);
}
```
