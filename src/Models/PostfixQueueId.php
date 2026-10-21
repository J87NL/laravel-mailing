<?php

declare(strict_types=1);

namespace J87NL\Mailing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $queue_id
 * @property string $message_id
 * @property Carbon|null $created_at
 */
class PostfixQueueId extends Model
{
    public const null UPDATED_AT = null;

    protected $table = 'mailing_postfix_queue_ids';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'queue_id',
        'message_id',
    ];
}
