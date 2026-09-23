<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Address extends Model
{
    protected $fillable = [
        'user_id', 'label', 'recipient', 'recipient_name', 'phone', 'line1', 'line2',
        'barangay', 'city', 'province', 'postal_code', 'country_code', 'is_default',
        'region_code', 'region_name', 'province_code', 'city_municipality_code', 'barangay_code',
    ];

    protected $casts = ['is_default' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getRecipientNameAttribute(mixed $value): ?string
    {
        return Schema::hasColumn('addresses', 'recipient_name')
            ? $value
            : ($this->attributes['recipient'] ?? null);
    }

    public function setRecipientNameAttribute(string $value): void
    {
        $this->attributes[Schema::hasColumn('addresses', 'recipient_name') ? 'recipient_name' : 'recipient'] = $value;
    }

    public function isStructured(): bool
    {
        return (bool) ($this->region_code && $this->city_municipality_code && $this->barangay_code);
    }

    public function formatted(): string
    {
        return collect([$this->line1, $this->line2, $this->barangay, $this->city, $this->province, $this->region_name, $this->postal_code])
            ->filter()->join(', ');
    }
}
