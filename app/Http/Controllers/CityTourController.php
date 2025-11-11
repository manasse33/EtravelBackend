<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CityTour;
use App\Models\Country;

class CityTourController extends Controller
{
    /**
     * Lister tous les City Tours
     */
    public function index()
    {
        $tours = CityTour::with('country')->get();
        return response()->json($tours);
    }

    /**
     * Créer un City Tour
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'country_id' => 'required|exists:countries,id',
            'date' => 'required|date',
            'prix' => 'required|numeric|min:0',
            'places_min' => 'required|integer|min:1',
            'places_max' => 'required|integer|min:1',
            'description' => 'nullable|string',
        ]);

        $tour = CityTour::create($validated);
        return response()->json(['message' => 'City Tour créé avec succès', 'data' => $tour], 201);
    }

    /**
     * Afficher un City Tour spécifique
     */
    public function show($id)
    {
        $tour = CityTour::with('country')->findOrFail($id);
        return response()->json($tour);
    }

    /**
     * Mettre à jour un City Tour
     */
    public function update(Request $request, $id)
    {
        $tour = CityTour::findOrFail($id);

        $validated = $request->validate([
            'nom' => 'sometimes|string|max:255',
            'country_id' => 'sometimes|exists:countries,id',
            'date' => 'sometimes|date',
            'prix' => 'sometimes|numeric|min:0',
            'places_min' => 'sometimes|integer|min:1',
            'places_max' => 'sometimes|integer|min:1',
            'description' => 'nullable|string',
        ]);

        $tour->update($validated);
        return response()->json(['message' => 'City Tour mis à jour avec succès', 'data' => $tour]);
    }

    /**
     * Supprimer un City Tour
     */
    public function destroy($id)
    {
        $tour = CityTour::findOrFail($id);
        $tour->delete();
        return response()->json(['message' => 'City Tour supprimé avec succès']);
    }
}
