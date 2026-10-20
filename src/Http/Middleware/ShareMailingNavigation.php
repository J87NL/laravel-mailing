<?php

declare(strict_types=1);

namespace J87NL\Mailing\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use J87NL\Mailing\Navigation\MailingNavigation;
use Symfony\Component\HttpFoundation\Response;

class ShareMailingNavigation
{
    public function __construct(
        private readonly MailingNavigation $navigation,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        Inertia::share('mailingNavigation', fn (): array => $user instanceof Authorizable ? $this->navigation->itemsFor($user, $request) : []);

        return $next($request);
    }
}
