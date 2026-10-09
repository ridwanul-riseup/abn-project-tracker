<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Change-history entry for a document. Content snapshots are not stored;
 * full content history is available through Git.
 *
 * @property int $id
 * @property int $document_id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $summary
 * @property string|null $content_hash
 * @property string|null $previous_hash
 * @property int $size_bytes
 * @property int $line_count
 * @property int $lines_delta
 * @property array<string, mixed>|null $metadata_changes
 * @property Carbon|null $created_at
 * @property-read User|null $user
 */
class DocumentRevision extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['metadata_changes' => 'array'];
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
