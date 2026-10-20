<?php

declare(strict_types=1);

namespace J87NL\Mailing\Actions;

use Illuminate\Support\Facades\DB;
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
    public function handle(Campaign $campaign, array $data, ?int $actorId): bool
    {
        return DB::transaction(function () use ($campaign, $data, $actorId): bool {
            if ($campaign->exists) {
                $current = Campaign::query()
                    ->lockForUpdate()
                    ->find($campaign->id);

                if (is_null($current) || ! $current->isEditable()) {
                    return false;
                }

                $campaign->setRawAttributes($current->getAttributes(), true);
            }

            $html = $this->sanitizer->sanitize(is_string($data['body_html'] ?? null) ? $data['body_html'] : '');
            $text = trim(is_string($data['body_text'] ?? null) ? $data['body_text'] : '');
            $preheader = trim(is_string($data['preheader'] ?? null) ? $data['preheader'] : '');

            $campaign->fill([
                'list_id' => (int) $data['list_id'],
                'subject' => trim((string) $data['subject']),
                'preheader' => $preheader === '' ? null : $preheader,
                'body_text' => $this->isGeneratedText($campaign, $text) ? $this->htmlToText->convert($html) : $text,
                'body_html' => $html,
            ]);

            if (! $campaign->exists) {
                $campaign->created_by = $actorId;
            }

            $campaign->save();

            return true;
        });
    }

    private function isGeneratedText(Campaign $campaign, string $text): bool
    {
        if ($text === '') {
            return true;
        }

        return $campaign->exists && $text === trim($this->htmlToText->convert($campaign->body_html));
    }
}
