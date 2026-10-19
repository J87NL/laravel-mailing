<?php

declare(strict_types=1);

namespace J87NL\Mailing\Bounces;

use J87NL\Mailing\Sending\Verp;

class DsnParser
{
    private const array VERP_HEADERS = [
        'x-original-to',
        'delivered-to',
        'envelope-to',
        'to',
    ];

    public function parse(string $raw): ?DsnReport
    {
        [$headers, $body] = $this->split($raw);
        $contentType = $headers['content-type'] ?? '';

        if (stripos($contentType, 'multipart/report') === false || stripos($contentType, 'delivery-status') === false) {
            return null;
        }

        $boundary = $this->parameter($contentType, 'boundary');

        if (is_null($boundary)) {
            return null;
        }

        $recipients = [];
        $originalMessageId = null;

        foreach ($this->parts($body, $boundary) as $part) {
            [$partHeaders, $partBody] = $this->split($part);
            $partType = strtolower($partHeaders['content-type'] ?? '');

            if (str_starts_with($partType, 'message/delivery-status')) {
                $recipients = $this->recipients($partBody);
            }

            if (str_starts_with($partType, 'text/rfc822-headers') || str_starts_with($partType, 'message/rfc822')) {
                [$original] = $this->split($partBody);
                $originalMessageId = isset($original['message-id']) ? trim($original['message-id']) : null;
            }
        }

        if ($recipients === []) {
            return null;
        }

        return new DsnReport($recipients, $this->verpSendId($headers), $originalMessageId);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function verpSendId(array $headers): ?int
    {
        foreach (self::VERP_HEADERS as $name) {
            foreach (preg_split('/[\s,]+/', $headers[$name] ?? '') ?: [] as $candidate) {
                $sendId = Verp::sendId($candidate);

                if (! is_null($sendId)) {
                    return $sendId;
                }
            }
        }

        return null;
    }

    /**
     * @return list<DsnRecipient>
     */
    private function recipients(string $body): array
    {
        $recipients = [];
        $blocks = preg_split("/\r?\n\s*\r?\n/", trim($body)) ?: [];

        foreach ($blocks as $block) {
            $fields = $this->fields($block);
            $recipient = $fields['final-recipient'] ?? $fields['original-recipient'] ?? null;

            if (is_null($recipient) || ! isset($fields['action'], $fields['status'])) {
                continue;
            }

            $address = trim((string) preg_replace('/^[^;]*;/', '', $recipient));

            $recipients[] = new DsnRecipient(
                recipient: strtolower($address),
                action: strtolower(trim($fields['action'])),
                status: trim(explode(' ', trim($fields['status']))[0]),
                diagnostic: isset($fields['diagnostic-code']) ? trim((string) preg_replace('/^[^;]*;/', '', $fields['diagnostic-code'])) : null,
            );
        }

        return $recipients;
    }

    /**
     * @return array{0: array<string, string>, 1: string}
     */
    private function split(string $raw): array
    {
        $parts = preg_split("/\r?\n\r?\n/", ltrim($raw, "\r\n"), 2) ?: [''];

        return [
            $this->fields($parts[0]),
            $parts[1] ?? '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function fields(string $block): array
    {
        $unfolded = (string) preg_replace("/\r?\n[ \t]+/", ' ', $block);
        $fields = [];

        foreach (preg_split("/\r?\n/", $unfolded) ?: [] as $line) {
            $position = strpos($line, ':');

            if ($position === false) {
                continue;
            }

            $name = strtolower(trim(substr($line, 0, $position)));

            if (isset($fields[$name])) {
                continue;
            }

            $fields[$name] = trim(substr($line, $position + 1));
        }

        return $fields;
    }

    /**
     * @return list<string>
     */
    private function parts(string $body, string $boundary): array
    {
        $chunks = explode(sprintf('--%s', $boundary), $body);
        $parts = [];

        foreach (array_slice($chunks, 1) as $chunk) {
            if (str_starts_with($chunk, '--')) {
                break;
            }

            $parts[] = ltrim($chunk, "\r\n");
        }

        return $parts;
    }

    private function parameter(string $header, string $name): ?string
    {
        if (preg_match(sprintf('/%s="?([^";]+)"?/i', preg_quote($name, '/')), $header, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
