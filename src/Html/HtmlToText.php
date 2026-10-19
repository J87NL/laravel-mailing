<?php

declare(strict_types=1);

namespace J87NL\Mailing\Html;

class HtmlToText
{
    public function convert(string $html): string
    {
        $text = (string) preg_replace_callback(
            '#<a\b[^>]*href=(["\'])(.*?)\1[^>]*>(.*?)</a>#is',
            fn (array $match): string => $this->link(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5), $match[3]),
            $html,
        );

        $text = (string) preg_replace('#<li\b[^>]*>#i', "\n- ", $text);
        $text = (string) preg_replace('#<br\s*/?>#i', "\n", $text);
        $text = (string) preg_replace('#</(p|h[1-6]|blockquote|ul|ol|table|tr)>#i', "\n\n", $text);
        $text = (string) preg_replace('#</t[dh]>#i', "\t", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5);
        $text = (string) preg_replace('/[ \t]+\n/', "\n", $text);
        $text = (string) preg_replace('/[ \t]{2,}/', ' ', $text);
        $text = (string) preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    private function link(string $href, string $label): string
    {
        $label = trim(html_entity_decode(strip_tags($label), ENT_QUOTES | ENT_HTML5));

        if ($label === '' || $label === $href) {
            return $href;
        }

        return sprintf('%s (%s)', $label, $href);
    }
}
