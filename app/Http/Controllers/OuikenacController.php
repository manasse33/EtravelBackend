<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OuikenacPackage;
use App\Models\PackagePrice;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OuikenacController extends Controller
{
    public function index(Request $request)
    {
        try {
            $packages = OuikenacPackage::with([
                'country',
                'prices.departureCountry',
                'prices.arrivalCountry',
                'prices.departureCity', 
                'prices.arrivalCity',
                'inclusions',
                'additionalCities'
            ])
            ->where('active', true)
            ->latest()
            ->get();

            return response()->json(['data' => $packages], 200);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Erreur lors du chargement des packages',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validatedPackage = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'image'=>'nullable|file|image|max:5120',
                'country_id' => 'required|exists:countries,id', 
            ]);
            
            // --- GESTION IMAGE PRINCIPALE (MANUELLE) ---
            if ($request->hasFile('image')) {
                $imageFile = $request->file('image');
                $folderName = 'packages/main';
                $destinationPath = storage_path("app/public/{$folderName}");
                $fileName = $imageFile->hashName(); 

                // Création du dossier avec permissions forcées
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0775, true);
                }
                
                // Déplacement manuel
                if ($imageFile->move($destinationPath, $fileName)) {
                    $validatedPackage['image'] = "{$folderName}/{$fileName}";
                } else {
                    throw new \Exception("Échec critique lors de l'upload de l'image.");
                }
            } else {
                $validatedPackage['image'] = null;
            }
            
            DB::beginTransaction();

            $package = OuikenacPackage::create($validatedPackage);

            // === GRIDS (prices) ===
            if ($request->has('grids')) {
                $grids = $request->input('grids');
                if (is_string($grids)) $grids = json_decode($grids, true);

                foreach ($grids as $index => $grid) {
                    $validator = Validator::make($grid, [
                        'country_id' => 'nullable|exists:countries,id',
                        'departure_country_id' => 'required|exists:countries,id',
                        'departure_city_id' => 'required|exists:cities,id',
                        'arrival_country_id' => 'nullable|exists:countries,id',
                        'arrival_city_id' => 'nullable|exists:cities,id',
                        'min_people' => 'nullable|integer|min:1',
                        'max_people' => 'nullable|integer|min:1',
                        'price_individual' => 'nullable', 
                        'price_group' => 'nullable',
                        'specific_inclusions' => 'nullable|string',
                        'currency' => 'required|in:CFA,USD,EUR',
                        'programme' => 'nullable|string',
                    ]);
                    $validatedGrid = $validator->validate();

                    // --- NETTOYAGE DES PRIX ---
                    $p_indiv = (isset($validatedGrid['price_individual']) && $validatedGrid['price_individual'] !== '') 
                                ? $validatedGrid['price_individual'] : null;
                                
                    $p_group = (isset($validatedGrid['price_group']) && $validatedGrid['price_group'] !== '') 
                                ? $validatedGrid['price_group'] : null;

                    // --- GESTION IMAGE GRILLE (MANUELLE) ---
                    $gridImagePath = null;
                    $imageField = "grids.$index.image"; // Accès via nom de champ de formulaire
                    
                    // Note: Il faut récupérer le fichier depuis la requête principale
                    if ($request->hasFile("grids.{$index}.image")) {
                        $gridImageFile = $request->file("grids.{$index}.image");
                        $gridFolderName = 'packages/grids';
                        $gridDestinationPath = storage_path("app/public/{$gridFolderName}");
                        $gridFileName = $gridImageFile->hashName();

                        if (!file_exists($gridDestinationPath)) {
                            mkdir($gridDestinationPath, 0775, true);
                        }

                        if ($gridImageFile->move($gridDestinationPath, $gridFileName)) {
                            $gridImagePath = "{$gridFolderName}/{$gridFileName}";
                        }
                    } else if (isset($grid['image']) && $request->hasFile("grids[$index][image]")) {
                        // Support pour notation array grids[0][image]
                         $gridImageFile = $request->file("grids[$index][image]");
                         // ... même logique ...
                         // Simplification : Laravel gère généralement bien les notations à points
                    }

                    // Fallback prix legacy
                    $defaultPrice = $p_group ?? $p_indiv ?? 0;

                    $package->prices()->create([
                        'country_id' => $package->country_id, 
                        'departure_country_id' => $validatedGrid['departure_country_id'],
                        'arrival_country_id' => $validatedGrid['arrival_country_id'] ?? null,
                        'departure_city_id' => $validatedGrid['departure_city_id'],
                        'arrival_city_id'   => $validatedGrid['arrival_city_id'] ?? null,
                        'min_people' => $validatedGrid['min_people'] ?? 1,
                        'max_people' => $validatedGrid['max_people'] ?? null,
                        'price' => $defaultPrice,
                        'price_individual' => $p_indiv,
                        'price_group' => $p_group,
                        'specific_inclusions' => $validatedGrid['specific_inclusions'] ?? null,
                        'currency' => $validatedGrid['currency'],
                        'programme' => $validatedGrid['programme'] ?? null,
                        'image' => $gridImagePath, 
                    ]);
                }
            }

            // === INCLUSIONS ===
            if ($request->has('inclusions')) {
                $inclusions = $request->input('inclusions');
                if (is_string($inclusions)) $inclusions = json_decode($inclusions, true);

                if ($this->isListOfStrings($inclusions)) {
                    foreach ($inclusions as $name) {
                        if(!empty($name)) {
                            $package->inclusions()->create(['name' => $name]);
                        }
                    }
                } else {
                    foreach ($inclusions as $inc) {
                        $name = is_array($inc) ? ($inc['name'] ?? '') : $inc;
                        if (!empty($name)) {
                            $package->inclusions()->create(['name' => $name]);
                        }
                    }
                }
            }
            
            DB::commit();

            return response()->json([
                'message' => 'Package créé avec succès',
                'data' => $package->load(['prices', 'inclusions'])
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => 'Erreur de validation', 'details' => $e->errors()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Erreur création', 'details' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $package = OuikenacPackage::with([
                'country',
                'prices.departureCountry',
                'prices.arrivalCountry',
                'inclusions',
                'additionalCities'
            ])->findOrFail($id);
            
            return response()->json($package, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Package introuvable'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Erreur lors de la récupération', 'details' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $package = OuikenacPackage::findOrFail($id);

            // 1. Validation
            $validatedPackage = $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'country_id' => 'required|exists:countries,id',
                'active' => 'boolean',
                'image' => 'nullable|file|image|max:5120'
            ]);

            // 2. Gestion Image Principale (MANUELLE)
            if ($request->hasFile('image')) {
                // Suppression de l'ancienne
                if ($package->image && file_exists(storage_path("app/public/{$package->image}"))) {
                    @unlink(storage_path("app/public/{$package->image}"));
                }

                $imageFile = $request->file('image');
                $folderName = 'packages/main';
                $destinationPath = storage_path("app/public/{$folderName}");
                $fileName = $imageFile->hashName();

                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0775, true);
                }

                if ($imageFile->move($destinationPath, $fileName)) {
                    $validatedPackage['image'] = "{$folderName}/{$fileName}";
                }
            } else {
                unset($validatedPackage['image']);
            }

            DB::beginTransaction();
            
            $package->update($validatedPackage);

            // 3. Gestion des Grilles (Prices)
            if ($request->has('grids')) {
                $grids = $request->input('grids');
                if (is_string($grids)) $grids = json_decode($grids, true);

                $processedGridIds = [];

                foreach ($grids as $index => $grid) {
                    $gridValidator = Validator::make($grid, [
                        'id' => 'nullable|integer|exists:package_prices,id',
                        'departure_country_id' => 'required|exists:countries,id',
                        'departure_city_id' => 'required|exists:cities,id',
                        'arrival_country_id' => 'nullable|exists:countries,id',
                        'arrival_city_id' => 'nullable|exists:cities,id',
                        'min_people' => 'nullable|integer|min:1',
                        'max_people' => 'nullable|integer|min:1',
                        'price_individual' => 'nullable', 
                        'price_group' => 'nullable',
                        'specific_inclusions' => 'nullable|string',
                        'programme' => 'nullable|string',
                        'currency' => 'required|string|max:10',
                    ]);
                    $validatedGrid = $gridValidator->validate();

                    // --- NETTOYAGE DES PRIX ---
                    $p_indiv = (isset($validatedGrid['price_individual']) && $validatedGrid['price_individual'] !== '') 
                                ? $validatedGrid['price_individual'] : null;
                                
                    $p_group = (isset($validatedGrid['price_group']) && $validatedGrid['price_group'] !== '') 
                                ? $validatedGrid['price_group'] : null;

                    $dataToSave = [
                        'country_id' => $package->country_id,
                        'departure_country_id' => $validatedGrid['departure_country_id'],
                        'departure_city_id' => $validatedGrid['departure_city_id'],
                        'arrival_country_id' => $validatedGrid['arrival_country_id'] ?? null,
                        'arrival_city_id' => $validatedGrid['arrival_city_id'] ?? null,
                        'min_people' => $validatedGrid['min_people'] ?? 1,
                        'max_people' => $validatedGrid['max_people'] ?? null,
                        'price_individual' => $p_indiv,
                        'price_group' => $p_group,
                        'specific_inclusions' => $validatedGrid['specific_inclusions'] ?? null,
                        'programme' => $validatedGrid['programme'] ?? null,
                        'currency' => $validatedGrid['currency'],
                        'price' => $p_group ?? $p_indiv ?? 0,
                    ];

                    // Gestion Image Grille (MANUELLE)
                    // Attention: la récupération du fichier dépend de la structure du formulaire (nested arrays)
                    // Dans un FormData, c'est souvent "grids[0][image]"
                    if ($request->hasFile("grids.{$index}.image")) {
                        $gridImage = $request->file("grids.{$index}.image");
                        $gFolder = 'packages/grids';
                        $gDestPath = storage_path("app/public/{$gFolder}");
                        $gName = $gridImage->hashName();

                        if (!file_exists($gDestPath)) {
                            mkdir($gDestPath, 0775, true);
                        }

                        if ($gridImage->move($gDestPath, $gName)) {
                            $dataToSave['image'] = "{$gFolder}/{$gName}";
                        }
                    }

                    if (!empty($validatedGrid['id'])) {
                        $existingPrice = PackagePrice::find($validatedGrid['id']);
                        if ($existingPrice) {
                            $existingPrice->update($dataToSave);
                            $processedGridIds[] = $existingPrice->id;
                        }
                    } else {
                        $newPrice = $package->prices()->create($dataToSave);
                        $processedGridIds[] = $newPrice->id;
                    }
                }

                if (count($processedGridIds) > 0) {
                    $package->prices()->whereNotIn('id', $processedGridIds)->delete();
                }
            }

            // 4. Inclusions (Sync)
            if ($request->has('inclusions')) {
                $package->inclusions()->delete();
                $inclusions = $request->input('inclusions');
                if (is_string($inclusions)) $inclusions = json_decode($inclusions, true);
                
                foreach ($inclusions as $incName) {
                    $name = is_array($incName) ? ($incName['name'] ?? '') : $incName;
                    if (!empty($name)) {
                        $package->inclusions()->create(['name' => $name]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Package mis à jour avec succès',
                'data' => $package->load(['prices', 'inclusions'])
            ], 200);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => 'Erreur de validation', 'details' => $e->errors()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Erreur MAJ', 'details' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $package = OuikenacPackage::findOrFail($id);
            
            // Suppression physique des images
            if ($package->image && file_exists(storage_path("app/public/{$package->image}"))) {
                @unlink(storage_path("app/public/{$package->image}"));
            }
            
            // On pourrait aussi boucler sur les grids pour supprimer leurs images
            foreach($package->prices as $price) {
                if ($price->image && file_exists(storage_path("app/public/{$price->image}"))) {
                     @unlink(storage_path("app/public/{$price->image}"));
                }
            }
            
            $package->delete();
            return response()->json(['message' => 'Package supprimé avec succès'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Package introuvable'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Erreur lors de la suppression', 'details' => $e->getMessage()], 500);
        }
    }

    private function isListOfStrings($arr)
    {
        if (!is_array($arr)) return false;
        foreach ($arr as $v) {
            if (!is_string($v)) return false;
        }
        return true;
    }
}