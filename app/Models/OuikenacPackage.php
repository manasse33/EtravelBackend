<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OuikenacPackage extends Model
{
    protected $fillable = ['title','description','image','active'];
    //  public function departureCountry() {
    //     return $this->belongsTo(Country::class, 'departure_country_id');
    // }

    public function additionalCities()
{
    // supposons que la table pivot s'appelle 'ouikenac_package_additional_cities'
    return $this->belongsToMany(City::class, 'package_cities', 'ouikenac_package_id', 'city_id')
                ->withPivot('type'); // si tu stockes le type d'escale
}



    public function prices() {
        return $this->morphMany(PackagePrice::class, 'priceable');
    }

    public function inclusions() {
        return $this->hasMany(PackageInclusion::class, 'package_id');
    }

    public function services() {
        return $this->morphToMany(Service::class, 'packageable', 'package_service')->withPivot('details');
    }

    public function reservations() {
        return $this->morphMany(Reservation::class, 'reservable');
    }
}
