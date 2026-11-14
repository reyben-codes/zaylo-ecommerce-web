<?php

namespace App\Models;

use App\Services\ChatMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticsMessage extends Model
{
    protected $fillable = ['logistics_thread_id', 'sender_id', 'client_id', 'body', 'image_path', 'video_path', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];

    /** What a conversation list shows for this message: its text, or "Photo" or "Video" when only one was sent. */
    public function getPreviewAttribute(): string
    {
        return ChatMedia::preview($this);
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(LogisticsThread::class, 'logistics_thread_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
