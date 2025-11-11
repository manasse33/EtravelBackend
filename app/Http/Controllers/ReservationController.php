<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Reservation;
use App\Models\CityTour;
use App\Models\DestinationPackage;
use App\Models\OuikenacPackage;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    /**
     * Lister toutes les réservations
     */
    public function index(Request $request)
    {
        try {
            $query = Reservation::with('reservable');

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $reservations = $query->orderBy('created_at', 'desc')->get();

            return response()->json($reservations);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des réservations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer une réservation pour n'importe quel package
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'reservable_id' => 'required|integer',
                'type' => 'required|string|in:city-tour,destination-package,ouikenac-package',
                'full_name' => 'required|string|max:150',
                'email' => 'required|email|max:150',
                'phone' => 'nullable|string|max:50',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date',
                'travelers' => 'nullable|integer|min:1',
                'currency' => 'nullable|string|max:10',
                'message' => 'nullable|string',
            ]);

            // Déterminer le modèle à partir du type
            $modelClass = match($data['type']) {
                'city-tour' => CityTour::class,
                'destination-package' => DestinationPackage::class,
                'ouikenac-package' => OuikenacPackage::class,
            };

            $package = $modelClass::findOrFail($data['reservable_id']);

            // Supprimer type et reservable_id du tableau avant création
            unset($data['type']);
            $reservation = $package->reservations()->create($data);

            return response()->json($reservation, 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation échouée',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création de la réservation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour le statut d'une réservation
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $reservation = Reservation::findOrFail($id);

            $request->validate([
                'status' => 'required|in:pending,approved,rejected,cancelled'
            ]);

            $reservation->status = $request->status;
            $reservation->validated_by = $request->user()?->id ?? null;
            $reservation->save();

            return response()->json($reservation);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation échouée',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour du statut',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
