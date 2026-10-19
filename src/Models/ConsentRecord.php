<?php

declare(strict_types=1);

namespace J87NL\Mailing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use J87NL\Mailing\Enums\ConsentAction;

/**
 * @property int $id
 * @property int $subscriber_id
 * @property int|null $list_id
 * @property ConsentAction $action
 * @property string|null $source
 * @property string|null $ip
 * @property string|null $note
 * @property int|null $actor_id
 * @property Carbon|null $created_at
 * @property-read MailingList|null $mailingList
 */
class ConsentRecord extends Model
{
    public const null UPDATED_AT = null;

    protected $table = 'mailing_consent_records';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'subscriber_id',
        'list_id',
        'action',
        'source',
        'ip',
        'note',
        'actor_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => ConsentAction::class,
            'actor_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MailingList, $this>
     */
    public function mailingList(): BelongsTo
    {
        return $this->belongsTo(MailingList::class, 'list_id');
    }
}
