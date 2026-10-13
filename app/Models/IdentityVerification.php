<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A buyer's uploaded government ID. Documents live on the private "local" disk and are only served to admins.
 */
class IdentityVerification extends Model
{
    public const ID_TYPES = [
        'philid' => 'PhilSys National ID (PhilID)',
        'drivers_license' => "Driver's License",
        'passport' => 'Passport',
        'umid' => 'UMID',
        'sss' => 'SSS ID',
        'prc' => 'PRC ID',
        'postal' => 'Postal ID',
        'voters' => "Voter's ID",
    ];

    protected $fillable = ['user_id', 'id_type', 'document_path', 'original_name', 'status', 'rejection_reason', 'reviewed_by', 'reviewed_at'];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getIdTypeLabelAttribute(): string
    {
        return self::ID_TYPES[$this->id_type] ?? $this->id_type;
    }
}
