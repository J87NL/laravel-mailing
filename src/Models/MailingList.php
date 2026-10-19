<?php

declare(strict_types=1);

namespace J87NL\Mailing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use J87NL\Mailing\Enums\SubscriptionStatus;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property string|null $mailer
 * @property string|null $sender_identity
 * @property string|null $reply_to
 * @property bool $is_public
 * @property int $throttle_per_minute
 * @property int|null $daily_cap
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MailingList extends Model
{
    protected $table = 'mailing_lists';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'name',
        'description',
        'mailer',
        'sender_identity',
        'reply_to',
        'is_public',
        'throttle_per_minute',
        'daily_cap',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_public' => true,
        'throttle_per_minute' => 20,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'throttle_per_minute' => 'integer',
            'daily_cap' => 'integer',
        ];
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'list_id');
    }

    public function subscribedCount(): int
    {
        return $this->subscriptions()
            ->where('status', SubscriptionStatus::Subscribed)
            ->count();
    }
}
