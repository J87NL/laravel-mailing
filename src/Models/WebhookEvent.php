<?php

declare(strict_types=1);

namespace J87NL\Mailing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $driver
 * @property string $event_id
 * @property string $event
 * @property Carbon|null $created_at
 */
class WebhookEvent extends Model
{
    public const null UPDATED_AT = null;

    protected $table = 'mailing_webhook_events';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'driver',
        'event_id',
        'event',
    ];
}
