<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'package_id' => 'required|exists:packages,id',
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'date_reservation' => 'required|date',
            'travelers' => 'required|integer|min:1',
            'message' => 'nullable|string',
        ]);

        $reservation = Reservation::create($validated);

        return response()->json([
            'message' => 'Réservation créée avec succès',
            'reservation' => $reservation
        ], 201);
    }

    public function index(Request $request)
    {
        $query = Reservation::with('package');

        if ($request->has('package_type')) {
            $query->whereHas('package', function($q) use ($request) {
                $q->where('package_type', $request->package_type);
            });
        }

        return $query->get();
    }
}
