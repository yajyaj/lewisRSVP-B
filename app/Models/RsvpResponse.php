<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RsvpResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_status',
        'party_size',
        'additional_guest_names',
        'dietary_or_accessibility_notes',
        'message',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'additional_guest_names' => 'array',
            'submitted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'revision' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at');
    }

    public function getReferenceAttribute(): string
    {
        return 'RSVP-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function eventProject(): BelongsTo
    {
        return $this->belongsTo(EventProject::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }
}
