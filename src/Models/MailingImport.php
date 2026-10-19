<?php

declare(strict_types=1);

namespace J87NL\Mailing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $list_id
 * @property string $filename
 * @property string $consent_source
 * @property string|null $consent_note
 * @property int $imported
 * @property int $skipped
 * @property int $confirmations_sent
 * @property array<int, string>|null $errors
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MailingList $mailingList
 */
class MailingImport extends Model
{
    protected $table = 'mailing_imports';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'list_id',
        'filename',
        'consent_source',
        'consent_note',
        'imported',
        'skipped',
        'confirmations_sent',
        'errors',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'imported' => 'integer',
            'skipped' => 'integer',
            'confirmations_sent' => 'integer',
            'errors' => 'array',
            'created_by' => 'integer',
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
