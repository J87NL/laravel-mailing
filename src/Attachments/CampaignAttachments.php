<?php

declare(strict_types=1);

namespace J87NL\Mailing\Attachments;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use J87NL\Mailing\Models\Campaign;
use J87NL\Mailing\Models\CampaignAttachment;
use RuntimeException;
use Throwable;

class CampaignAttachments
{
    private const int MAX_NAME_LENGTH = 150;

    /**
     * @return list<string>
     */
    public function extensions(): array
    {
        $configured = config('mailing.attachments.extensions', []);
        $extensions = array_values(array_unique(array_map(
            fn (string $extension): string => strtolower(ltrim(trim($extension), '.')),
            array_filter(is_array($configured) ? $configured : [], 'is_string'),
        )));

        if (in_array('jpg', $extensions, true) && ! in_array('jpeg', $extensions, true)) {
            $extensions[] = 'jpeg';
        }

        return $extensions;
    }

    public function maxTotalBytes(): int
    {
        return max(0, (int) config('mailing.attachments.max_total_kilobytes', 10240)) * 1024;
    }

    public function warnTotalBytes(): int
    {
        return max(0, (int) config('mailing.attachments.warn_total_kilobytes', 2048)) * 1024;
    }

    public function defaultAsLink(): bool
    {
        return (bool) config('mailing.attachments.default_as_link', false);
    }

    public function linkValidDays(): int
    {
        return max(1, (int) config('mailing.attachments.link_valid_days', 30));
    }

    public function retentionDays(): int
    {
        return max($this->linkValidDays(), (int) config('mailing.attachments.retention_days', 30));
    }

    public function store(Campaign $campaign, UploadedFile $file, bool $asLink): CampaignAttachment
    {
        $disk = (string) config('mailing.attachments.disk', 'local');
        $extension = strtolower((string) ($file->guessExtension() ?? $file->getClientOriginalExtension()));
        $token = Str::random(40);
        $directory = sprintf('%s/%d', trim((string) config('mailing.attachments.directory', 'mailing/attachments'), '/'), $campaign->id);
        $path = Storage::disk($disk)->putFileAs($directory, $file, sprintf('%s.%s', $token, $extension));

        if ($path === false) {
            throw new RuntimeException(sprintf('The attachment could not be written to disk "%s".', $disk));
        }

        try {
            return CampaignAttachment::query()
                ->create([
                    'campaign_id' => $campaign->id,
                    'token' => $token,
                    'disk' => $disk,
                    'path' => $path,
                    'name' => $this->displayName($file, $extension),
                    'mime_type' => (string) ($file->getMimeType() ?? 'application/octet-stream'),
                    'size' => (int) $file->getSize(),
                    'as_link' => $asLink,
                ]);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }
    }

    /**
     * @return list<CampaignAttachment>
     */
    public function missing(Campaign $campaign): array
    {
        return array_values($campaign->attachments()
            ->get()
            ->reject(fn (CampaignAttachment $attachment): bool => $this->exists($attachment))
            ->all());
    }

    public function exists(CampaignAttachment $attachment): bool
    {
        return Storage::disk($attachment->disk)->exists($attachment->path);
    }

    public function linkExpiry(): CarbonImmutable
    {
        return CarbonImmutable::now()->addDays($this->linkValidDays());
    }

    public function downloadUrl(CampaignAttachment $attachment, CarbonInterface $until): string
    {
        return URL::temporarySignedRoute('mailing.attachments.download', $until, [
            'token' => $attachment->token,
        ]);
    }

    public static function size(int $bytes): string
    {
        return Number::fileSize($bytes, maxPrecision: 1);
    }

    private function displayName(UploadedFile $file, string $extension): string
    {
        $base = pathinfo(str_replace('\\', '/', $file->getClientOriginalName()), PATHINFO_FILENAME);
        $base = trim((string) preg_replace('/[\x00-\x1F\x7F\/"]+/u', '', $base));
        $base = Str::limit($base, self::MAX_NAME_LENGTH - strlen($extension) - 1, '');

        if ($base === '') {
            $base = (string) __('mailing::campaigns.attachments.fallback_name');
        }

        return sprintf('%s.%s', $base, $extension);
    }
}
