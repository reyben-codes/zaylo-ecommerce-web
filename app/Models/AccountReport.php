<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountReport extends Model
{
    public const CATEGORIES = [
        'misconduct' => 'Inappropriate behavior', 'harassment' => 'Harassment or abusive messages',
        'fraud' => 'Suspected fraud or misleading information', 'mishandling' => 'Order or service mishandling',
        'safety' => 'Safety concern', 'other' => 'Other concern',
    ];

    public const STATUSES = ['open' => 'Open', 'in_review' => 'Under review', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'];

    protected $guarded = [];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function reporter() { return $this->belongsTo(User::class, 'reporter_id'); }
    public function reportedUser() { return $this->belongsTo(User::class, 'reported_user_id'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
