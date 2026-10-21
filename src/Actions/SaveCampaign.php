<?php

declare(strict_types=1);

namespace J87NL\Mailing\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use J87NL\Mailing\Attachments\CampaignAttachments;
use J87NL\Mailing\Contracts\HtmlSanitizer;
use J87NL\Mailing\Html\HtmlToText;
use J87NL\Mailing\Models\Campaign;
use J87NL\Mailing\Models\CampaignAttachment;
use Throwable;

class SaveCampaign
{
    public function __construct(
        private readonly HtmlSanitizer $sanitizer,
        private readonly HtmlToText $htmlToText,
        private readonly CampaignAttachments $attachments,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Campaign $campaign, array $data, ?int $actorId): bool
    {
        $stored = [];

        try {
            return DB::transaction(function () use ($campaign, $data, $actorId, &$stored): bool {
                $hadAttachments = false;

                if ($campaign->exists) {
                    $current = Campaign::query()
                        ->lockForUpdate()
                        ->find($campaign->id);

                    if (is_null($current) || ! $current->isEditable()) {
                        return false;
                    }

                    $campaign->setRawAttributes($current->getAttributes(), true);
                    $hadAttachments = $campaign->attachments()
                        ->exists();
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

                if ($hadAttachments) {
                    $this->updateAttachments($campaign, $data);
                }

                $uploads = $this->uploads($data);
                $total = (int) $campaign->attachments()->sum('size') + array_sum(array_map(fn (UploadedFile $file): int => (int) $file->getSize(), $uploads));

                if ($uploads !== [] && $total > $this->attachments->maxTotalBytes()) {
                    throw ValidationException::withMessages([
                        'attachments' => __('mailing::campaigns.errors.attachments_too_large', [
                            'max' => CampaignAttachments::size($this->attachments->maxTotalBytes()),
                        ]),
                    ]);
                }

                foreach ($uploads as $file) {
                    $attachment = $this->attachments->store($campaign, $file, (bool) ($data['new_attachments_as_link'] ?? false));
                    $stored[] = [
                        $attachment->disk,
                        $attachment->path,
                    ];
                }

                return true;
            });
        } catch (Throwable $exception) {
            foreach ($stored as [$disk, $path]) {
                Storage::disk($disk)->delete($path);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateAttachments(Campaign $campaign, array $data): void
    {
        $removed = $this->keys($data['remove_attachments'] ?? []);
        $links = $this->keys($data['attachment_links'] ?? []);

        $campaign->attachments()
            ->get()
            ->each(function (CampaignAttachment $attachment) use ($removed, $links): void {
                if (in_array((string) $attachment->id, $removed, true)) {
                    $attachment->delete();

                    return;
                }

                $attachment->as_link = in_array((string) $attachment->id, $links, true);
                $attachment->save();
            });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<UploadedFile>
     */
    private function uploads(array $data): array
    {
        $files = $data['attachments'] ?? [];

        return array_values(array_filter(is_array($files) ? $files : [], fn (mixed $file): bool => $file instanceof UploadedFile));
    }

    /**
     * @return list<string>
     */
    private function keys(mixed $values): array
    {
        return array_values(array_map(
            fn (mixed $value): string => (string) $value,
            array_filter(is_array($values) ? $values : [], 'is_scalar'),
        ));
    }

    private function isGeneratedText(Campaign $campaign, string $text): bool
    {
        if ($text === '') {
            return true;
        }

        return $campaign->exists && $text === trim($this->htmlToText->convert($campaign->body_html));
    }
}
