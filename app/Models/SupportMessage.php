<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    protected $fillable = ['support_thread_id', 'sender_id', 'client_id', 'body', 'image_path', 'read_at'];

    /** What a list preview or the app shows for a message: its text, or "Photo" when only a photo was sent. */
    public function getPreviewAttribute(): string
    {
        return filled($this->body) ? (string) $this->body : ($this->image_path ? '📷 Photo' : '');
    }

    protected $casts = ['read_at' => 'datetime'];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(SupportThread::class, 'support_thread_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
