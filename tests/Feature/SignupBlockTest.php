<?php

declare(strict_types=1);

use J87NL\Cms\Actions\ChangePageStatus;
use J87NL\Cms\Actions\SaveBlock;
use J87NL\Cms\Actions\SavePage;
use J87NL\Cms\Blocks\BlockRegistry;
use J87NL\Cms\Enums\PageStatus;
use J87NL\Cms\Models\Block;
use J87NL\Cms\Models\Page;
use J87NL\Mailing\Signup\SignupGuard;

/**
 * @param  array<string, mixed>  $data
 */
function pageWithSignupBlock(array $data): Page
{
    $home = app(SavePage::class)->handle(new Page, [
        'title' => 'Welkom',
    ], null);
    app(ChangePageStatus::class)->handle($home, PageStatus::Published, null);

    $page = app(SavePage::class)->handle(new Page, [
        'title' => 'Nieuwsbrief',
        'slug' => 'nieuwsbrief',
    ], null);

    app(ChangePageStatus::class)->handle($page, PageStatus::Published, null);
    app(SaveBlock::class)->handle($page, new Block, app(BlockRegistry::class)->find('mailing_signup'), $data, null);

    return $page;
}

beforeEach(function (): void {
    $this->list = makeList();
});

test('the sign-up block renders a form for its list that returns to the page', function (): void {
    pageWithSignupBlock([
        'list' => 'nieuws',
        'title' => 'Blijf op de hoogte',
    ]);

    $response = $this->get('/nieuwsbrief')
        ->assertOk()
        ->assertSee('Blijf op de hoogte')
        ->assertSee(sprintf('action="%s"', route('mailing.subscribe.store', $this->list)), false)
        ->assertSee('name="return_to" value="/nieuwsbrief"', false)
        ->assertSee(sprintf('name="%s"', SignupGuard::HONEYPOT), false);

    expect($response->headers->getCookies())->toBeEmpty();

    $this->get('/nieuwsbrief?aangemeld=nieuws')
        ->assertOk()
        ->assertSee(__('mailing::signup.check_inbox'));
});

test('the sign-up block links the configured privacy statement', function (): void {
    config()->set('mailing.signup.privacy_url', '/privacy');

    pageWithSignupBlock([
        'list' => 'nieuws',
        'title' => 'Blijf op de hoogte',
    ]);

    $this->get('/nieuwsbrief')
        ->assertOk()
        ->assertSee('href="/privacy"', false)
        ->assertSee(__('mailing::signup.privacy_link'));
});

test('the sign-up block only accepts public lists', function (): void {
    makeList([
        'key' => 'intern',
        'name' => 'Intern',
        'is_public' => false,
    ]);

    $type = app(BlockRegistry::class)->find('mailing_signup');

    expect(validator(['list' => 'intern'], $type->rules())->fails())->toBeTrue()
        ->and(validator(['list' => 'nieuws'], $type->rules())->passes())->toBeTrue();
});

test('the sign-up block renders nothing once its list is no longer public', function (): void {
    pageWithSignupBlock([
        'list' => 'nieuws',
        'title' => 'Blijf op de hoogte',
    ]);

    $this->list->update([
        'is_public' => false,
    ]);

    $this->get('/nieuwsbrief')
        ->assertOk()
        ->assertDontSee('mailing-signup', false)
        ->assertDontSee(route('mailing.subscribe.store', $this->list), false);
});
