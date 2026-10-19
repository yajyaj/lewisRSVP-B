<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventProject extends Model
{
    use HasFactory;

    protected $fillable = [
        'internal_name',
        'public_slug',
        'combined_title',
        'landing_title',
        'landing_subtitle',
        'welcome_message',
        'event_date',
        'start_time',
        'end_time',
        'timezone',
        'event_stops',
        'venue_name',
        'venue_address',
        'map_url',
        'rsvp_deadline',
        'cover_image_url',
        'gallery_urls',
        'theme',
        'access_mode',
        'allow_plus_ones',
        'max_party_size',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'rsvp_deadline' => 'datetime',
            'gallery_urls' => 'array',
            'event_stops' => 'array',
            'allow_plus_ones' => 'boolean',
        ];
    }

    public function editVersion(): string
    {
        return hash('sha256', serialize([$this->getRawOriginal(), $this->occasionLabels()->pluck('label')->all()]));
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        return $user->isSuperAdmin() ? $query : $query->where('owner_id', $user->id);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function occasionLabels(): HasMany
    {
        return $this->hasMany(OccasionLabel::class)->orderBy('display_order');
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
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
