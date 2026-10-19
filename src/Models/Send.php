<?php

declare(strict_types=1);

namespace J87NL\Mailing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use J87NL\Mailing\Enums\BounceType;
use J87NL\Mailing\Enums\SendStatus;

/**
 * @property int $id
 * @property int $campaign_id
 * @property int|null $subscription_id
 * @property string $email
 * @property SendStatus $status
 * @property string|null $message_id
 * @property int $attempts
 * @property Carbon|null $next_attempt_at
 * @property Carbon|null $sending_started_at
 * @property string|null $last_response
 * @property Carbon|null $handed_over_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $bounced_at
 * @property BounceType|null $bounce_type
 * @property string|null $bounce_diagnostic
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read Subscription|null $subscription
 */
class Send extends Model
{
    protected $table = 'mailing_sends';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'campaign_id',
        'subscription_id',
        'email',
        'status',
    ];

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SendStatus::class,
            'attempts' => 'integer',
            'next_attempt_at' => 'datetime',
            'sending_started_at' => 'datetime',
            'handed_over_at' => 'datetime',
            'delivered_at' => 'datetime',
            'bounced_at' => 'datetime',
            'bounce_type' => BounceType::class,
        ];
    }
}
