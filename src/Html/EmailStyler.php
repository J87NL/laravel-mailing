<?php

declare(strict_types=1);

namespace J87NL\Mailing\Html;

class EmailStyler
{
    private const array STYLES = [
        'p' => 'margin:0 0 16px;font-size:16px;line-height:1.6;color:#1f2937;',
        'h2' => 'margin:24px 0 12px;font-size:22px;line-height:1.3;color:#111827;',
        'h3' => 'margin:20px 0 10px;font-size:18px;line-height:1.3;color:#111827;',
        'h4' => 'margin:16px 0 8px;font-size:16px;line-height:1.3;color:#111827;',
        'a' => 'color:#1d4ed8;text-decoration:underline;',
        'ul' => 'margin:0 0 16px;padding-left:24px;',
        'ol' => 'margin:0 0 16px;padding-left:24px;',
        'li' => 'margin:0 0 6px;font-size:16px;line-height:1.6;color:#1f2937;',
        'blockquote' => 'margin:0 0 16px;padding:8px 16px;border-left:4px solid #d1d5db;color:#4b5563;',
        'table' => 'width:100%;border-collapse:collapse;margin:0 0 16px;',
        'th' => 'padding:6px 8px;border:1px solid #d1d5db;text-align:left;font-size:15px;',
        'td' => 'padding:6px 8px;border:1px solid #d1d5db;font-size:15px;',
    ];

    public function inline(string $html): string
    {
        return (string) preg_replace_callback(
            '#<(p|h2|h3|h4|a|ul|ol|li|blockquote|table|th|td)\b([^>]*)>#i',
            function (array $match): string {
                $tag = strtolower($match[1]);

                if (stripos($match[2], 'style=') !== false) {
                    return $match[0];
                }

                return sprintf('<%s%s style="%s">', $match[1], $match[2], self::STYLES[$tag]);
            },
            $html,
        );
    }
}
