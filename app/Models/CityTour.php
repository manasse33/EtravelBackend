<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CityTour extends Model {
    protected $fillable = ['title','description','image','country_id','city_id','scheduled_date','itinerary','min_people','max_people','active'];
    public function city(){ return $this->belongsTo(City::class); }
    public function country(){ return $this->belongsTo(Country::class); }
    public function prices(){ return $this->morphMany(PackagePrice::class, 'priceable'); }
    public function services(){ return $this->morphToMany(Service::class,'packageable','package_service')->withPivot('details'); }
    public function reservations(){ return $this->morphMany(Reservation::class,'reservable'); }

    // Dans App\Models\OuikenacPackage.php (et les autres)

protected static function boot()
{
    parent::boot();

    // Lors de la suppression du Package (deleting)
    static::deleting(function ($package) {
        // On supprime tous les prix liés automatiquement
        $package->prices()->delete();
    });
}
}
