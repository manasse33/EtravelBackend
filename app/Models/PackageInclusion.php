<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageInclusion extends Model
{
    protected $fillable = [
        'ouikenac_package_id',
        'name',
        'description',
    ];

    public function package()
    {
        return $this->belongsTo(OuikenacPackage::class, 'ouikenac_package_id');
    }
}
