<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionImport extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PARSING = 'parsing';

    public const STATUS_PARSED = 'parsed';

    public const STATUS_IMPORTING = 'importing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'upload_id',
        'status',
        'mapping',
        'options',
        'total_rows',
        'processed_rows',
        'created_count',
        'skipped_count',
        'error_count',
        'errors',
        'meta',
        'message',
    ];

    protected $casts = [
        'mapping' => 'array',
        'options' => 'array',
        'errors' => 'array',
        'meta' => 'array',
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
        'created_count' => 'integer',
        'skipped_count' => 'integer',
        'error_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }
}
