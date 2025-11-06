<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',         // Nom du forfait
        'description',   // Description
        'image',         // URL image
        'package_type',  // 'ouikenac', 'city_tour', 'destination'
        'sub_type',      // 'libota', 'premium', 'acces' (uniquement si package_type = ouikenac)
        'country',       // 'rc', 'rdc' ou null si pas de pays
    ];

    // Les prix liés à ce package
    public function prices()
    {
        return $this->hasMany(PackagePrice::class);
    }

    // Les réservations liées
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
}
