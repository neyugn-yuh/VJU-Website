<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    use Auditable;

    public const STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'spam' => 'Spam', 'trash' => 'Trash'];

    /** Public submissions are not audited; moderation actions are. */
    protected array $auditEvents = ['updated', 'deleted'];

    protected $guarded = ['id'];

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
