<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DestinationPackage extends Model
{
    protected $fillable = [
        'title',
        'description',
        'image',
        'departure_country_id',
        'active'
    ];

    public function departureCountry() {
        return $this->belongsTo(Country::class, 'departure_country_id');
    }

    public function prices() {
        return $this->morphMany(PackagePrice::class, 'priceable');
    }

    public function reservations() {
        return $this->morphMany(Reservation::class, 'reservable');
    }
}
