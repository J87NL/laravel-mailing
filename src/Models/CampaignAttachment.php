<?php

declare(strict_types=1);

namespace J87NL\Mailing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $campaign_id
 * @property string $token
 * @property string $disk
 * @property string $path
 * @property string $name
 * @property string $mime_type
 * @property int $size
 * @property bool $as_link
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 */
class CampaignAttachment extends Model
{
    protected $table = 'mailing_campaign_attachments';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'campaign_id',
        'token',
        'disk',
        'path',
        'name',
        'mime_type',
        'size',
        'as_link',
    ];

    /**
     * @var list<string>
     */
    protected $touches = [
        'campaign',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'as_link' => false,
    ];

    protected static function booted(): void
    {
        static::deleted(function (CampaignAttachment $attachment): void {
            $disk = $attachment->disk;
            $path = $attachment->path;

            DB::afterCommit(fn () => Storage::disk($disk)->delete($path));
        });
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'as_link' => 'boolean',
        ];
    }
}
