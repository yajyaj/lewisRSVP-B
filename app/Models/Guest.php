<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guest extends Model
{
    use HasFactory;

    protected $fillable = ['full_name', 'email', 'phone'];

    public function editVersion(): string
    {
        return hash('sha256', serialize([$this->id, $this->full_name, $this->email, $this->phone, $this->updated_at?->format('Y-m-d H:i:s')]));
    }

    public function eventProject(): BelongsTo
    {
        return $this->belongsTo(EventProject::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function rsvpResponses(): HasMany
    {
        return $this->hasMany(RsvpResponse::class);
    }
}
