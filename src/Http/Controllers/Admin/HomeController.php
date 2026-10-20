<?php

declare(strict_types=1);

namespace J87NL\Mailing\Http\Controllers\Admin;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use J87NL\Mailing\Navigation\MailingNavigation;

class HomeController extends Controller
{
    public function __invoke(Request $request, MailingNavigation $navigation): RedirectResponse
    {
        $user = $request->user();
        $url = $user instanceof Authorizable ? $navigation->firstUrlFor($user) : null;

        abort_if(is_null($url), 403);

        return redirect($url);
    }
}
