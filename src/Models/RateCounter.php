<?php

declare(strict_types=1);

namespace J87NL\Mailing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $key
 * @property Carbon $window_starts_at
 * @property int $hits
 */
class RateCounter extends Model
{
    public $timestamps = false;

    protected $table = 'mailing_rate_counters';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'window_starts_at',
        'hits',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'window_starts_at' => 'datetime',
            'hits' => 'integer',
        ];
    }
}
