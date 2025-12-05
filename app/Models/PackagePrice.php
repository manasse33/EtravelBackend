<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PackagePrice extends Model {
    protected $fillable = ['priceable_type','priceable_id','country_id','departure_country_id','arrival_country_id','departure_city_id',
        'arrival_city_id','min_people','max_people','price','currency','programme'];
    public function priceable(){ return $this->morphTo(); }
    public function departureCountry() {
    return $this->belongsTo(Country::class, 'departure_country_id');
}

public function arrivalCountry() {
    return $this->belongsTo(Country::class, 'arrival_country_id');
}

public function departureCity()
{
    return $this->belongsTo(City::class, 'departure_city_id');
}

public function arrivalCity()
{
    return $this->belongsTo(City::class, 'arrival_city_id');
}

}
