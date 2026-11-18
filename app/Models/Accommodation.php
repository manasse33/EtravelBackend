<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;

class Accommodation extends Model
{
    protected $fillable = [
        'type', 'name', 'description', 'address', 
        'city_id', 'image', 'gallery_images', 'stars', 'active'
    ];

    protected $casts = [
        'active' => 'boolean',
        'stars' => 'integer',
        'gallery_images' => 'array', // Indispensable : convertit le JSON en tableau PHP
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(AccommodationRoom::class);
    }

    public function reservations(): MorphMany
    { 
        return $this->morphMany(Reservation::class, 'reservable'); 
    }

    // --- NETTOYAGE AUTOMATIQUE DES FICHIERS LORS DE LA SUPPRESSION ---
    protected static function boot() {
        parent::boot();
        
        static::deleting(function($accommodation) {
             // 1. Supprimer l'image principale
             if ($accommodation->image) {
                 $path = storage_path("app/public/{$accommodation->image}");
                 if (file_exists($path)) @unlink($path);
             }

             // 2. Supprimer les images de la GALERIE
             if ($accommodation->gallery_images) {
                 foreach($accommodation->gallery_images as $galleryImg) {
                     $gPath = storage_path("app/public/{$galleryImg}");
                     if (file_exists($gPath)) @unlink($gPath);
                 }
             }

             // 3. Supprimer les images des chambres
             foreach($accommodation->rooms as $room) {
                 if ($room->image) {
                     $roomPath = storage_path("app/public/{$room->image}");
                     if (file_exists($roomPath)) @unlink($roomPath);
                 }
             }
        });
    }

    public function search(Request $request)
    {
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $cityId = $request->query('city_id');
        $roomMorphType = (new AccommodationRoom())->getMorphClass();

        $query = Accommodation::with(['rooms' => function($q) use ($dateFrom, $dateTo, $roomMorphType) {
            // On ne veut charger que les chambres disponibles
            if ($dateFrom && $dateTo) {
                $q->whereRaw("
                    (SELECT COUNT(*) FROM reservations 
                     WHERE reservations.reservable_id = accommodation_rooms.id 
                     AND reservations.reservable_type = ?
                     AND reservations.status != 'cancelled'
                     AND reservations.date_from < ? 
                     AND reservations.date_to > ?) < accommodation_rooms.available_qty
                ", [$roomMorphType, $dateTo, $dateFrom]);
            }
        }]);

        if ($cityId) {
            $query->where('city_id', $cityId);
        }

        $accommodations = $query->whereHas('rooms')->get();

        return response()->json(['data' => $accommodations]);
    }
}
