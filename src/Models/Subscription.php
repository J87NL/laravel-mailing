<?php

declare(strict_types=1);

namespace J87NL\Mailing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use J87NL\Mailing\Enums\SubscriptionStatus;

/**
 * @property int $id
 * @property int $subscriber_id
 * @property int $list_id
 * @property SubscriptionStatus $status
 * @property string $unsubscribe_token
 * @property Carbon|null $consent_at
 * @property string|null $consent_ip
 * @property string|null $consent_source
 * @property Carbon|null $confirmation_sent_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $unsubscribed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Subscriber $subscriber
 * @property-read MailingList $mailingList
 */
class Subscription extends Model
{
    protected $table = 'mailing_subscriptions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'subscriber_id',
        'list_id',
        'status',
        'consent_at',
        'consent_ip',
        'consent_source',
        'confirmation_sent_at',
        'confirmed_at',
        'unsubscribed_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $subscription): void {
            if (($subscription->unsubscribe_token ?? '') !== '') {
                return;
            }

            $subscription->unsubscribe_token = Str::random(48);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'consent_at' => 'datetime',
            'confirmation_sent_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Subscriber, $this>
     */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    /**
     * @return BelongsTo<MailingList, $this>
     */
    public function mailingList(): BelongsTo
    {
        return $this->belongsTo(MailingList::class, 'list_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            SubscriptionStatus::Pending,
            SubscriptionStatus::Subscribed,
        ], true);
    }
}
