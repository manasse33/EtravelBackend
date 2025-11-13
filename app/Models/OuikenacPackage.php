<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OuikenacPackage extends Model
{
    protected $fillable = ['title','description','image','active'];

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
