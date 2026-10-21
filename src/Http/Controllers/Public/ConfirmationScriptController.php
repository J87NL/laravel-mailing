<?php

declare(strict_types=1);

namespace J87NL\Mailing\Http\Controllers\Public;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class ConfirmationScriptController extends Controller
{
    public const string FORM_ATTRIBUTE = 'data-mailing-auto-submit';

    public function __invoke(): Response
    {
        return response((string) file_get_contents(__DIR__.'/../../../../resources/scripts/confirm.js'), 200, [
            'Content-Type' => 'text/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
