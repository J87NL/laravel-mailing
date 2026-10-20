<?php

declare(strict_types=1);

namespace J87NL\Mailing\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowSameOriginScripts
{
    private const string HEADER = 'Content-Security-Policy';

    private const string SCRIPT_DIRECTIVE = "script-src 'self'";

    private const array FALLBACK_POLICY = [
        "default-src 'self'",
        self::SCRIPT_DIRECTIVE,
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
    ];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $policy = $response->headers->get(self::HEADER);

        if (is_null($policy) || trim($policy) === '') {
            $response->headers->set(self::HEADER, implode('; ', self::FALLBACK_POLICY));

            return $response;
        }

        $directives = array_values(array_filter(
            array_map('trim', explode(';', $policy)),
            fn (string $directive): bool => $directive !== '' && ! str_starts_with(strtolower($directive), 'script-src'),
        ));

        $directives[] = self::SCRIPT_DIRECTIVE;

        $response->headers->set(self::HEADER, implode('; ', $directives));

        return $response;
    }
}
