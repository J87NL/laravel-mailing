<?php

declare(strict_types=1);

namespace J87NL\Mailing\Actions;

use J87NL\Mailing\Contracts\HtmlSanitizer;
use J87NL\Mailing\Html\HtmlToText;
use J87NL\Mailing\Models\Campaign;

class SaveCampaign
{
    public function __construct(
        private readonly HtmlSanitizer $sanitizer,
        private readonly HtmlToText $htmlToText,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Campaign $campaign, array $data, ?int $actorId): Campaign
    {
        $html = $this->sanitizer->sanitize(is_string($data['body_html'] ?? null) ? $data['body_html'] : '');
        $text = trim(is_string($data['body_text'] ?? null) ? $data['body_text'] : '');
        $preheader = trim(is_string($data['preheader'] ?? null) ? $data['preheader'] : '');

        $campaign->fill([
            'list_id' => (int) $data['list_id'],
            'subject' => trim((string) $data['subject']),
            'preheader' => $preheader === '' ? null : $preheader,
            'body_html' => $html,
            'body_text' => $text === '' ? $this->htmlToText->convert($html) : $text,
        ]);

        if (! $campaign->exists) {
            $campaign->created_by = $actorId;
        }

        $campaign->save();

        return $campaign;
    }
}
