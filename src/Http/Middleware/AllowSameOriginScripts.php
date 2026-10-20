<?php

declare(strict_types=1);

namespace J87NL\Mailing\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowSameOriginScripts
{
    private const string HEADER = 'Content-Security-Policy';

    private const string SCRIPT_DIRECTIVE = 'script-src';

    private const string SELF = "'self'";

    private const string NONE = "'none'";

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $policies = $response->headers->all(strtolower(self::HEADER));

        if ($policies === []) {
            return $response;
        }

        $response->headers->set(self::HEADER, array_map(fn (?string $policy): string => $this->allowSelf((string) $policy), $policies));

        return $response;
    }

    private function allowSelf(string $policy): string
    {
        $directives = [];
        $hasScriptDirective = false;

        foreach (explode(';', $policy) as $directive) {
            $directive = trim($directive);

            if ($directive === '') {
                continue;
            }

            $parts = preg_split('/\s+/', $directive) ?: [];

            if (strtolower($parts[0] ?? '') !== self::SCRIPT_DIRECTIVE) {
                $directives[] = $directive;

                continue;
            }

            $hasScriptDirective = true;
            $sources = array_values(array_diff(array_slice($parts, 1), [
                self::NONE,
                self::SELF,
            ]));

            $directives[] = implode(' ', [
                self::SCRIPT_DIRECTIVE,
                self::SELF,
                ...$sources,
            ]);
        }

        if (! $hasScriptDirective) {
            $directives[] = sprintf('%s %s', self::SCRIPT_DIRECTIVE, self::SELF);
        }

        return implode('; ', $directives);
    }
}
