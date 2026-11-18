<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Post extends Model
{
    protected $fillable = [
        'type', 'category', 'title', 'content', 'excerpt', 'image', 'location', 'active'
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function reservations(): MorphMany
    {
        return $this->morphMany(Reservation::class, 'reservable');
    }
}