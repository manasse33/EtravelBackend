<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CityTour;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Exception;

class CityTourController extends Controller
{
    /**
     * Lister tous les City Tours
     */
    public function index()
    {
        $tours = CityTour::with(['country', 'city', 'prices'])->get();
        return response()->json($tours);
    }

    /**
     * Créer un City Tour
     */
    public function store(Request $request)
    {
        try {
            // Validation du package
            $validated = $request->validate([
                'nom' => 'required|string|max:255',
                'country_id' => 'required|exists:countries,id',
                'city_id' => 'required|exists:cities,id',
                'date' => 'required|date',
                'places_min' => 'required|integer|min:1',
                'places_max' => 'required|integer|min:1|gte:places_min',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'currency' => 'required|in:CFA,USD,EUR',
                'image' => 'nullable|file|image|max:5120', // max 5MB
                'programme'=>'nullable|string'
            ]);

            DB::beginTransaction();

            // Upload image si présent
            if ($request->hasFile('image')) {
                $validated['image'] = $request->file('image')->store('city_tours', 'public');
            }

            // Création du tour
            $tour = CityTour::create([
                'title' => $validated['nom'],
                'country_id' => $validated['country_id'],
                'city_id' => $validated['city_id'],
                'scheduled_date' => $validated['date'],
                'min_people' => $validated['places_min'],
                'max_people' => $validated['places_max'],
                'description' => $validated['description'] ?? null,
                'image' => $validated['image'] ?? null,
                'active' => true,
            ]);

            // Création du price
            $tour->prices()->create([
                'price' => $validated['price'],
                'currency' => $validated['currency'],
                'min_people' => $validated['places_min'],
                'max_people' => $validated['places_max'],
                'programme' => $validated['programme'],
            ]);

            DB::commit();

            return response()->json([
                'message' => 'City Tour créé avec succès',
                'data' => $tour->load('prices')
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validation échouée',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            DB::rollBack();
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
            $tour = CityTour::with(['country', 'city', 'prices'])->findOrFail($id);
            return response()->json($tour);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'City Tour non trouvé'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur serveur',
                'error' => $e->getMessage()
            ], 500);
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
                'places_max' => 'sometimes|integer|min:1|gte:places_min',
                'description' => 'nullable|string',
                'price' => 'sometimes|numeric|min:0',
                'currency' => 'sometimes|in:CFA,USD,EUR',
                'image' => 'nullable|file|image|max:5120',
                'programme'=>'nullable|string'
            ]);

            DB::beginTransaction();

            // Upload image si présent
            if ($request->hasFile('image')) {
                $validated['image'] = $request->file('image')->store('city_tours', 'public');
            }

            // Préparer les données à update
            $updateData = [];
            if (isset($validated['nom'])) $updateData['title'] = $validated['nom'];
            if (isset($validated['date'])) $updateData['scheduled_date'] = $validated['date'];
            if (isset($validated['places_min'])) $updateData['min_people'] = $validated['places_min'];
            if (isset($validated['places_max'])) $updateData['max_people'] = $validated['places_max'];
            if (isset($validated['country_id'])) $updateData['country_id'] = $validated['country_id'];
            if (isset($validated['city_id'])) $updateData['city_id'] = $validated['city_id'];
            if (isset($validated['description'])) $updateData['description'] = $validated['description'];
            if (isset($validated['image'])) $updateData['image'] = $validated['image'];
            

            $tour->update($updateData);

            // Mise à jour ou création du price
            if (isset($validated['price'])) {
                $tour->prices()->updateOrCreate(
                    ['priceable_id' => $tour->id, 'priceable_type' => CityTour::class],
                    [
                        'price' => $validated['price'],
                        'currency' => $validated['currency'] ?? 'CFA',
                        'min_people' => $validated['places_min'] ?? $tour->min_people,
                        'max_people' => $validated['places_max'] ?? $tour->max_people,
                        'programme' => $validated['programme'] ?? $tour->programme,
                    ]
                );
            }

            DB::commit();

            return response()->json([
                'message' => 'City Tour mis à jour avec succès',
                'data' => $tour->load('prices')
            ], 200);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validation échouée',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Une erreur est survenue lors de la mise à jour',
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
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'City Tour non trouvé'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur serveur',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
