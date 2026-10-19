<?php

declare(strict_types=1);

namespace J87NL\Mailing\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use J87NL\Mailing\Enums\SuppressionReason;
use J87NL\Mailing\Support\EmailAddress;

/**
 * @property int $id
 * @property string $email
 * @property SuppressionReason $reason
 * @property string|null $source
 * @property string|null $note
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Suppression extends Model
{
    protected $table = 'mailing_suppressions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'reason',
        'source',
        'note',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => SuppressionReason::class,
            'created_by' => 'integer',
        ];
    }

    public static function covers(string $email): bool
    {
        return self::query()
            ->where('email', EmailAddress::normalize($email))
            ->exists();
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
