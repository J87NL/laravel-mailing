<?php

declare(strict_types=1);

namespace J87NL\Mailing\Html;

use J87NL\Mailing\Contracts\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonySanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class BasicHtmlSanitizer implements HtmlSanitizer
{
    private const array ELEMENTS = [
        'p',
        'br',
        'h2',
        'h3',
        'h4',
        'strong',
        'b',
        'em',
        'i',
        'blockquote',
        'ul',
        'ol',
        'li',
        'table',
        'thead',
        'tbody',
        'tr',
    ];

    private ?SymfonySanitizer $sanitizer = null;

    public function sanitize(?string $html): string
    {
        if (is_null($html) || trim($html) === '') {
            return '';
        }

        return $this->sanitizer()->sanitize($html);
    }

    private function sanitizer(): SymfonySanitizer
    {
        if (! is_null($this->sanitizer)) {
            return $this->sanitizer;
        }

        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes([
                'http',
                'https',
                'mailto',
                'tel',
            ])
            ->allowRelativeLinks(false);

        foreach (self::ELEMENTS as $element) {
            $config = $config->allowElement($element);
        }

        $config = $config
            ->allowElement('a', [
                'href',
                'title',
            ])
            ->allowElement('th', [
                'colspan',
                'rowspan',
                'scope',
            ])
            ->allowElement('td', [
                'colspan',
                'rowspan',
            ]);

        return $this->sanitizer = new SymfonySanitizer($config);
    }
}
