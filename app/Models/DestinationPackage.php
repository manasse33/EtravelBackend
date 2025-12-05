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
