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
        'arrival_country_id', // ✅ tu l’avais oublié
        'arrival_city_id',
        'min_people',
        'max_people',
        'active'
    ];

    public function departureCountry() {
        return $this->belongsTo(Country::class, 'departure_country_id');
    }

    public function arrivalCountry() {
        return $this->belongsTo(Country::class, 'arrival_country_id');
    }

    public function prices() {
        return $this->morphMany(PackagePrice::class, 'priceable');
    }

    public function services() {
        return $this->morphToMany(Service::class, 'packageable', 'package_service')->withPivot('details');
    }

    public function reservations() {
        return $this->morphMany(Reservation::class, 'reservable');
    }
}
