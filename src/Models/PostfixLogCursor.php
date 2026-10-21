<?php

declare(strict_types=1);

namespace J87NL\Mailing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $path
 * @property int $inode
 * @property int $position
 * @property Carbon|null $updated_at
 */
class PostfixLogCursor extends Model
{
    public const null CREATED_AT = null;

    protected $table = 'mailing_postfix_log_cursors';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'path',
        'inode',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inode' => 'integer',
            'position' => 'integer',
        ];
    }
}
