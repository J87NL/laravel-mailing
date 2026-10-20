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
        $script = sprintf("var form = document.querySelector('form[%s]');\nif (form) {\n    form.submit();\n}\n", self::FORM_ATTRIBUTE);

        return response($script, 200, [
            'Content-Type' => 'text/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
