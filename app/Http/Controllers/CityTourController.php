<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CityTour;

class CityTourController extends Controller
{
    /**
     * Lister tous les City Tours
     */
    public function index()
    {
        $tours = CityTour::with(['country', 'city','prices'])->get();
        return response()->json($tours);
    }

    /**
     * Créer un City Tour
     */
    public function store(Request $request)
    {
        try {
            // Validation des données
            $validated = $request->validate([
                'nom' => 'required|string|max:255',
                'country_id' => 'required|exists:countries,id',
                'city_id' => 'required|exists:cities,id',
                'date' => 'required|date',
                'places_min' => 'required|integer|min:1',
                'places_max' => 'required|integer|min:1',
                'description' => 'nullable|string',
                 'price' => 'required|numeric|min:0'
            ]);

            // Création du CityTour
            $tour = CityTour::create([
                'title' => $validated['nom'],
                'country_id' => $validated['country_id'],
                'city_id' => $validated['city_id'],
                'scheduled_date' => $validated['date'],
                'min_people' => $validated['places_min'],
                'max_people' => $validated['places_max'],
                'description' => $validated['description'] ?? null,
                'active' => true
            ]);

              $tour->prices()->create([
                'price' => $validated['price'],
                'currency' => 'CFA',
                'min_people' => $validated['places_min'],
                'max_people' => $validated['places_max'],
            ]);
            return response()->json([
                'message' => 'City Tour créé avec succès',
                'data' => $tour
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation échouée',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Une erreur est survenue lors de la création du City Tour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher un City Tour spécifique
     */
    public function show($id)
    {
        try {
            $tour = CityTour::with(['country', 'city','prices'])->findOrFail($id);
            return response()->json($tour);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'City Tour non trouvé',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Mettre à jour un City Tour
     */
    public function update(Request $request, $id)
    {
        try {
            $tour = CityTour::findOrFail($id);

            $validated = $request->validate([
                'nom' => 'sometimes|string|max:255',
                'country_id' => 'sometimes|exists:countries,id',
                'city_id' => 'sometimes|exists:cities,id',
                'date' => 'sometimes|date',
                'places_min' => 'sometimes|integer|min:1',
                'places_max' => 'sometimes|integer|min:1',
                'description' => 'nullable|string',
            ]);

            $updateData = [];
            if (isset($validated['nom'])) $updateData['title'] = $validated['nom'];
            if (isset($validated['date'])) $updateData['scheduled_date'] = $validated['date'];
            if (isset($validated['places_min'])) $updateData['min_people'] = $validated['places_min'];
            if (isset($validated['places_max'])) $updateData['max_people'] = $validated['places_max'];
            if (isset($validated['country_id'])) $updateData['country_id'] = $validated['country_id'];
            if (isset($validated['city_id'])) $updateData['city_id'] = $validated['city_id'];
            if (isset($validated['description'])) $updateData['description'] = $validated['description'];

            $tour->update($updateData);

            return response()->json([
                'message' => 'City Tour mis à jour avec succès',
                'data' => $tour
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation échouée',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Une erreur est survenue lors de la mise à jour du City Tour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un City Tour
     */
    public function destroy($id)
    {
        try {
            $tour = CityTour::findOrFail($id);
            $tour->delete();
            return response()->json(['message' => 'City Tour supprimé avec succès']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Une erreur est survenue lors de la suppression du City Tour',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
