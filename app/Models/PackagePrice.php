<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PackagePrice extends Model {
    protected $fillable = ['priceable_type','priceable_id','country_id','departure_country_id','arrival_country_id','min_people','max_people','price','currency','programme'];
    public function priceable(){ return $this->morphTo(); }
}
