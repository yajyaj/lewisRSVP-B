<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OccasionLabel extends Model
{
    use HasFactory;

    protected $fillable = ['label', 'display_order'];

    public function eventProject(): BelongsTo
    {
        return $this->belongsTo(EventProject::class);
    }
}
