<?php

declare(strict_types=1);

namespace J87NL\Mailing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use J87NL\Mailing\Enums\CampaignStatus;

/**
 * @property int $id
 * @property int $list_id
 * @property string $subject
 * @property string|null $preheader
 * @property string $body_html
 * @property string $body_text
 * @property CampaignStatus $status
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MailingList $mailingList
 */
class Campaign extends Model
{
    protected $table = 'mailing_campaigns';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'list_id',
        'subject',
        'preheader',
        'body_html',
        'body_text',
        'created_by',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return BelongsTo<MailingList, $this>
     */
    public function mailingList(): BelongsTo
    {
        return $this->belongsTo(MailingList::class, 'list_id');
    }

    /**
     * @return HasMany<Send, $this>
     */
    public function sends(): HasMany
    {
        return $this->hasMany(Send::class);
    }

    public function isEditable(): bool
    {
        return $this->status === CampaignStatus::Draft;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'created_by' => 'integer',
        ];
    }
}
