<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Reservation extends Model {
    protected $fillable = ['reservable_type','reservable_id','full_name','email','phone','date_from','date_to','travelers','total_price','currency','message','status','validated_by'];
    public function reservable(){ return $this->morphTo(); }
    public function validator(){ return $this->belongsTo(Admin::class,'validated_by'); }
}
