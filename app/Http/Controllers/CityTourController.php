<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CityTour;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Exception;
// On garde Storage pour la suppression, mais on utilisera move() pour l'upload
use Illuminate\Support\Facades\Storage; 

class CityTourController extends Controller
{
    /**
     * Lister tous les City Tours
     */
    public function index()
    {
        $tours = CityTour::with(['country', 'city', 'prices'])
                    ->latest()
                    ->get();
        return response()->json($tours);
    }

    /**
     * Créer un City Tour
     */
    public function store(Request $request)
    {
        try {
            // 1. Validation
            $validated = $request->validate([
                'nom' => 'required|string|max:255',
                'country_id' => 'required|exists:countries,id',
                'city_id' => 'required|exists:cities,id',
                'date' => 'required|date',
                'places_min' => 'required|integer|min:1',
                'places_max' => 'required|integer|min:1|gte:places_min',
                'description' => 'nullable|string',
                'itinerary' => 'nullable|string',
                'programme' => 'nullable|string',
                
                // Prix nullable pour permettre le nettoyage
                'price_individual' => 'nullable', 
                'price_group' => 'nullable',
                'currency' => 'required|in:CFA,USD,EUR',
                
                'image' => 'nullable|file|image|max:5120',
            ]);

            DB::beginTransaction();
            
            // 2. GESTION IMAGE (Méthode Manuelle qui fonctionne chez vous)
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imageFile = $request->file('image');
                $folderName = 'city_tours';
                // On force le chemin absolu vers storage/app/public
                $destinationPath = storage_path("app/public/{$folderName}");
                $fileName = $imageFile->hashName();

                // Création manuelle du dossier avec permissions 0775
                if (!file_exists($destinationPath)) {
                    if (!mkdir($destinationPath, 0775, true)) {
                         throw new \Exception("Échec de création du dossier: {$destinationPath}");
                    }
                }
                
                // Déplacement manuel du fichier
                if ($imageFile->move($destinationPath, $fileName)) {
                    $imagePath = "{$folderName}/{$fileName}";
                } else {
                    throw new \Exception("Échec du déplacement de l'image.");
                }
            }

            // 3. Création du City Tour
            $tour = CityTour::create([
                'title' => $validated['nom'],
                'country_id' => $validated['country_id'],
                'city_id' => $validated['city_id'],
                'scheduled_date' => $validated['date'],
                'min_people' => $validated['places_min'],
                'max_people' => $validated['places_max'],
                'description' => $validated['description'] ?? null,
                'itinerary' => $validated['itinerary'] ?? null,
                'image' => $imagePath, // Le chemin généré manuellement
                'active' => true,
            ]);

            // 4. Nettoyage des Prix
            $p_indiv = ($validated['price_individual'] !== '' && $validated['price_individual'] !== null) 
                        ? $validated['price_individual'] : null;
            
            $p_group = ($validated['price_group'] !== '' && $validated['price_group'] !== null) 
                        ? $validated['price_group'] : null;

            // Fallback pour le champ legacy 'price'
            $mainPrice = $p_group ?? $p_indiv ?? 0;

            // 5. Création du Prix associé
            $tour->prices()->create([
                'price' => $mainPrice,
                'price_individual' => $p_indiv,
                'price_group' => $p_group,
                'currency' => $validated['currency'],
                'min_people' => $validated['places_min'],
                'max_people' => $validated['places_max'],
                'programme' => $validated['programme'] ?? null,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'City Tour créé avec succès',
                'data' => $tour->load('prices')
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur de validation', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            \Log::error('Erreur Store CityTour: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur lors de la création.', 'error' => $e->getMessage()], 500);
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
            return response()->json(['message' => 'City Tour introuvable'], 404);
        }
    }

    /**
     * Mettre à jour un City Tour
     */
    public function update(Request $request, $id)
    {
        try {
            $tour = CityTour::findOrFail($id);

            // 1. Validation
            $validated = $request->validate([
                'nom' => 'sometimes|string|max:255',
                'country_id' => 'sometimes|exists:countries,id',
                'city_id' => 'sometimes|exists:cities,id',
                'date' => 'sometimes|date',
                'places_min' => 'sometimes|integer|min:1',
                'places_max' => 'sometimes|integer|min:1|gte:places_min',
                'description' => 'nullable|string',
                'itinerary' => 'nullable|string',
                'programme' => 'nullable|string',
                'price_individual' => 'nullable',
                'price_group' => 'nullable',
                'currency' => 'sometimes|in:CFA,USD,EUR',
                'image' => 'nullable|file|image|max:5120',
                'active' => 'boolean'
            ]);

            DB::beginTransaction();

            // 2. GESTION IMAGE (Méthode Manuelle)
            if ($request->hasFile('image')) {
                // Tenter de supprimer l'ancienne image si elle existe
                if ($tour->image) {
                    $oldPath = storage_path("app/public/{$tour->image}");
                    if (file_exists($oldPath)) {
                        @unlink($oldPath); // @ pour éviter erreur si fichier déjà parti
                    }
                }
                
                $imageFile = $request->file('image');
                $folderName = 'city_tours';
                $destinationPath = storage_path("app/public/{$folderName}");
                $fileName = $imageFile->hashName();

                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0775, true);
                }
                
                if ($imageFile->move($destinationPath, $fileName)) {
                    $tour->image = "{$folderName}/{$fileName}";
                }
            }

            // 3. Mise à jour des champs principaux
            if (isset($validated['nom'])) $tour->title = $validated['nom'];
            if (isset($validated['country_id'])) $tour->country_id = $validated['country_id'];
            if (isset($validated['city_id'])) $tour->city_id = $validated['city_id'];
            if (isset($validated['date'])) $tour->scheduled_date = $validated['date'];
            if (isset($validated['places_min'])) $tour->min_people = $validated['places_min'];
            if (isset($validated['places_max'])) $tour->max_people = $validated['places_max'];
            if (array_key_exists('description', $validated)) $tour->description = $validated['description'];
            if (array_key_exists('itinerary', $validated)) $tour->itinerary = $validated['itinerary'];
            if (isset($validated['active'])) $tour->active = $validated['active'];

            $tour->save();

            // 4. Mise à jour des Prix
            $price = $tour->prices()->firstOrNew([]);

            if (isset($validated['currency'])) $price->currency = $validated['currency'];
            if (isset($validated['places_min'])) $price->min_people = $validated['places_min'];
            if (isset($validated['places_max'])) $price->max_people = $validated['places_max'];
            if (array_key_exists('programme', $validated)) $price->programme = $validated['programme'];

            if (array_key_exists('price_individual', $validated)) {
                $price->price_individual = ($validated['price_individual'] !== '' && $validated['price_individual'] !== null) 
                    ? $validated['price_individual'] : null;
            }

            if (array_key_exists('price_group', $validated)) {
                $price->price_group = ($validated['price_group'] !== '' && $validated['price_group'] !== null) 
                    ? $validated['price_group'] : null;
            }

            $price->price = $price->price_group ?? $price->price_individual ?? $price->price ?? 0;

            $price->save();

            DB::commit();

            return response()->json([
                'message' => 'City Tour mis à jour avec succès',
                'data' => $tour->load('prices')
            ], 200);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur de validation', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            \Log::error('Erreur Update CityTour: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur lors de la mise à jour.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Supprimer un City Tour
     */
    public function destroy($id)
    {
        try {
            $tour = CityTour::findOrFail($id);
            
            // Suppression manuelle du fichier
            if ($tour->image) {
                $filePath = storage_path("app/public/{$tour->image}");
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }

            $tour->delete();
            
            return response()->json(['message' => 'City Tour supprimé avec succès']);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'City Tour introuvable'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'Erreur serveur', 'error' => $e->getMessage()], 500);
        }
    }
}