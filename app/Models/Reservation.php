<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'package_id',
        'full_name',
        'email',
        'phone',
        'date_reservation',
        'travelers',
        'message',
    ];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}
