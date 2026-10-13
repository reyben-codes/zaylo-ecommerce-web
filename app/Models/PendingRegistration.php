<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A sign-up waiting for its email code. Details (including the hashed password) are stored encrypted;
 * uploaded documents wait in a temporary folder until the account is created.
 */
class PendingRegistration extends Model
{
    protected $fillable = ['token', 'email', 'role', 'payload', 'documents', 'code_hash', 'attempts', 'code_expires_at', 'code_sent_at', 'expires_at'];

    protected $casts = [
        'payload' => 'encrypted:array',
        'documents' => 'array',
        'code_expires_at' => 'datetime',
        'code_sent_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = ['payload', 'code_hash'];
}
