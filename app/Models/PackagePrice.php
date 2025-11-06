<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackagePrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'package_id',
        'min_people',
        'max_people',
        'price',
        'currency',
    ];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}
