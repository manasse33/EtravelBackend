<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Carbon\Carbon;

class AccommodationRoom extends Model
{
    protected $fillable = [
        'accommodation_id', 'title', 'description', 'price', 
        'currency', 'capacity_adults', 'capacity_children', 
        'available_qty', 'image'
    ];

        protected $appends = ['availability_status'];
    protected $casts = [
        'price' => 'decimal:2',
        'capacity_adults' => 'integer',
        'capacity_children' => 'integer',
        'available_qty' => 'integer',
    ];

    public function accommodation(): BelongsTo
    {
        return $this->belongsTo(Accommodation::class);
    }

    // C'EST ICI QUE LA MAGIE OPÈRE POUR TON FORMULAIRE REACT
    public function reservations(): MorphMany
    {
        return $this->morphMany(Reservation::class, 'reservable');
    }

       public function isAvailable($dateFrom, $dateTo)
    {
        // 1. Compter combien de réservations CONFIRMÉES ou EN ATTENTE chevauchent ces dates
        $reservedCount = $this->reservations()
            ->where('status', '!=', 'cancelled') // On ignore les annulées
            ->where(function ($query) use ($dateFrom, $dateTo) {
                $query->where('date_from', '<', $dateTo)
                      ->where('date_to', '>', $dateFrom);
            })
            ->count();

        // 2. Vérifier par rapport à la quantité totale de cette chambre dans l'hôtel
        // Si on a 3 chambres de ce type et 3 réservations, c'est plein.
        // Si on a 3 chambres et 2 réservations, il en reste 1.
        return $reservedCount < $this->available_qty;
    }

     public function getAvailabilityStatusAttribute()
    {
        $today = Carbon::today();

        // On compte les réservations ACTIVES qui chevauchent "Aujourd'hui"
        $activeReservations = $this->reservations()
            ->where('status', '!=', 'rejected') // On ignore les annulées
            ->where('date_from', '<=', $today)   // A commencé avant ou ajd
            ->where('date_to', '>', $today)      // Ne finit pas ajd (finit demain ou plus tard)
            ->get();

        $count = $activeReservations->count();

        // Cas 1 : Il reste de la place (Reservations < Quantité totale de la chambre)
        if ($count < $this->available_qty) {
            return [
                'is_available' => true,
                'message' => 'Disponible',
                'next_date' => null
            ];
        }

        // Cas 2 : C'est complet. On cherche la date de libération.
        // On prend la date de fin la plus lointaine parmi les réservations actuelles
        // (Simplification : pour être ultra précis il faudrait vérifier les trous dans le calendrier, 
        // mais pour un affichage simple, prendre la fin de la réservation en cours suffit).
        $lastReservationEnd = $activeReservations->max('date_to');
        
        // On formate la date pour l'affichage
        $nextDate = $lastReservationEnd ? Carbon::parse($lastReservationEnd)->format('Y-m-d') : null;

        return [
            'is_available' => false,
            'message' => 'Indisponible',
            'next_date' => $nextDate
        ];
    }
}