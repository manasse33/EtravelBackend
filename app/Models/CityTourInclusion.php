<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CityTourInclusion extends Model
{
    protected $fillable = [
        'city_tour_id',
        'name',
        'description',
    ];

    public function cityTour()
    {
        return $this->belongsTo(CityTour::class);
    }
}
