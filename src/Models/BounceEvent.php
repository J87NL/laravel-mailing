<?php

declare(strict_types=1);

namespace J87NL\Mailing\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use J87NL\Mailing\Enums\BounceType;
use J87NL\Mailing\Support\EmailAddress;

/**
 * @property int $id
 * @property string $email
 * @property int|null $send_id
 * @property BounceType $type
 * @property string $source
 * @property string|null $diagnostic
 * @property Carbon|null $created_at
 */
class BounceEvent extends Model
{
    public const null UPDATED_AT = null;

    protected $table = 'mailing_bounce_events';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'send_id',
        'type',
        'source',
        'diagnostic',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BounceType::class,
        ];
    }

    /**
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => EmailAddress::normalize($value),
        );
    }
}
