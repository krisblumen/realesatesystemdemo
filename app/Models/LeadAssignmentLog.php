<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadAssignmentLog extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'lead_id',
        'from_agent_id',
        'to_agent_id',
        'assigned_by_id',
        'source',
        'reason',
    ];

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<User, $this> */
    public function fromAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_agent_id');
    }

    /** @return BelongsTo<User, $this> */
    public function toAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_agent_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }
}
