<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OuikenacPackage;
use App\Models\PackageInclusion;
use App\Models\PackagePrice;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OuikenacController extends Controller
{
    public function index(Request $request)
    {
        try {
            $packages = OuikenacPackage::with(['prices', 'inclusions', 'additionalCities'])->get();
            return response()->json($packages, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Erreur lors du chargement des packages', 'details' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            // validation minimale pour la création du package (title obligatoire)
            $validatedPackage = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                
                'image'=>'required|file'
            ]);

            DB::beginTransaction();

            $package = OuikenacPackage::create($validatedPackage);

            // === GRIDS (prices) ===
            // on attend un tableau 'grids' (array). Les images peuvent être envoyées en tant que fichiers avec
            // les champs "grids.0.image", "grids.1.image" etc (multipart/form-data).
           if ($request->has('grids')) {
    $grids = $request->input('grids');

    // Si c'est une string JSON, décoder en tableau
    if (is_string($grids)) {
        $grids = json_decode($grids, true);
        if (!is_array($grids)) {
            throw new \Exception("Les grids doivent être un tableau valide.");
        }
    }

    foreach ($grids as $index => $grid) {
        // validation par grille
        $validator = validator($grid, [
            'country_id' => 'nullable|exists:countries,id',
            'departure_country_id' => 'required|exists:countries,id',
            'arrival_country_id' => 'nullable|exists:countries,id',
            'departure_city_id' => 'required|exists:cities,id',
            'arrival_city_id' => 'nullable|exists:cities,id',
            'min_people' => 'required|integer|min:1',
            'max_people' => 'nullable|integer|min:1',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|in:CFA,USD,EUR',
            'programme' => 'nullable|string',
        ]);
        $validatedGrid = $validator->validate();

        // gérer l'image si fournie en fichier: input name grids.0.image
        $imageField = "grids.$index.image";
        if ($request->hasFile($imageField)) {
            $path = $request->file($imageField)->store('packages', 'public');
            $validatedGrid['image'] = $path;
        }

        // créer le price (morph)
        $package->prices()->create([
            'country_id' => $validatedGrid['country_id'] ?? null,
            'departure_country_id' => $validatedGrid['departure_country_id'],
            'arrival_country_id' => $validatedGrid['arrival_country_id'] ?? null,
            'min_people' => $validatedGrid['min_people'],
            'max_people' => $validatedGrid['max_people'] ?? null,
            'price' => $validatedGrid['price'],
            'currency' => $validatedGrid['currency'],
            'programme' => $validatedGrid['programme'] ?? null,
        ]);
    }
}


            // === INCLUSIONS ===
            // on attend un tableau d'objets {name, description?} ou simplement une liste de strings
            if ($request->has('inclusions')) {
                $inclusions = $request->input('inclusions');

                // si c'est une simple liste de strings => transform
                if ($this->isListOfStrings($inclusions)) {
                    foreach ($inclusions as $name) {
                        $package->inclusions()->create(['name' => $name]);
                    }
                } else {
                    foreach ($inclusions as $inc) {
                        $validatedInc = validator($inc, [
                            'name' => 'required|string|max:150',
                            'description' => 'nullable|string',
                        ])->validate();

                        $package->inclusions()->create($validatedInc);
                    }
                }
            }

            // === VILLES ADDITIONNELLES ===
            // on attend un tableau 'additional_cities' = [{city_id: X, type: 'escale'}] or [cityId, cityId]
            if ($request->has('additional_cities')) {
                $cities = $request->input('additional_cities');
                foreach ($cities as $c) {
                    if (is_array($c)) {
                        $cityId = $c['city_id'] ?? null;
                        $type = $c['type'] ?? null;
                    } else {
                        $cityId = $c;
                        $type = null;
                    }
                    if ($cityId) {
                        // attach via pivot
                        $package->additionalCities()->attach($cityId, ['type' => $type]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Package créé avec succès',
                'data' => $package->load(['prices', 'inclusions', 'additionalCities'])
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => 'Erreur de validation', 'details' => $e->errors()], 422);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Erreur lors de la création du package', 'details' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $package = OuikenacPackage::with(['departureCountry', 'arrivalCountry', 'prices', 'inclusions', 'additionalCities'])->findOrFail($id);
            return response()->json($package, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Package introuvable'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Erreur lors de la récupération du package', 'details' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $package = OuikenacPackage::findOrFail($id);

            $validatedPackage = $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'departure_country_id' => 'sometimes|exists:countries,id',
                'departure_city_id' => 'sometimes|exists:cities,id',
                'arrival_country_id' => 'sometimes|exists:countries,id',
                'arrival_city_id' => 'sometimes|exists:cities,id',
                'min_people' => 'sometimes|integer|min:1',
                'max_people' => 'sometimes|integer|min:1',
                'active' => 'sometimes|boolean',
                
            ]);

            DB::beginTransaction();

            $package->update($validatedPackage);

            // === Mettre à jour / ajouter des grids ===
            if ($request->has('grids')) {
                $grids = $request->input('grids');
                foreach ($grids as $index => $grid) {
                    // si grid contient 'id' => update, sinon create
                    $gridValidator = validator($grid, [
                        'id' => 'nullable|integer|exists:package_prices,id',
                        'country_id' => 'nullable|exists:countries,id',
                        'departure_country_id' => 'required|exists:countries,id',
                        'arrival_country_id' => 'nullable|exists:countries,id',
                        'departure_city_id' => 'required|exists:cities,id',
                        'arrival_city_id' => 'nullable|exists:cities,id',
                        'min_people' => 'required|integer|min:1',
                        'max_people' => 'nullable|integer|min:1',
                        'price' => 'required|numeric|min:0',
                        'currency' => 'required|string|max:10',
                        'programme'=>'nullable|string'
                    ]);
                    $validatedGrid = $gridValidator->validate();

                    // handle image file
                    $imageField = "grids.$index.image";
                    if ($request->hasFile($imageField)) {
                        $path = $request->file($imageField)->store('packages', 'public');
                        $validatedGrid['image'] = $path;
                    }

                    if (!empty($validatedGrid['id'])) {
                        // update existing price
                        $price = PackagePrice::find($validatedGrid['id']);
                        if ($price) {
                            $price->update([
                                'country_id' => $validatedGrid['country_id'] ?? $price->country_id,
                                'departure_country_id' => $validatedGrid['departure_country_id'],
                                'arrival_country_id' => $validatedGrid['arrival_country_id'] ?? $price->arrival_country_id,
                                'departure_city_id' => $validatedGrid['departure_city_id'],
                                'arrival_city_id' => $validatedGrid['arrival_city_id'] ?? $price->arrival_city_id,
                                'min_people' => $validatedGrid['min_people'],
                                'max_people' => $validatedGrid['max_people'] ?? $price->max_people,
                                'price' => $validatedGrid['price'],
                                'currency' => $validatedGrid['currency'],
                                'programme' => $validatedGrid['programme'] 
                            ]);
                        }
                    } else {
                        // create new price
                        $package->prices()->create([
                            'country_id' => $validatedGrid['country_id'] ?? null,
                            'departure_country_id' => $validatedGrid['departure_country_id'],
                            'arrival_country_id' => $validatedGrid['arrival_country_id'] ?? null,
                            'departure_city_id' => $validatedGrid['departure_city_id'],
                            'arrival_city_id' => $validatedGrid['arrival_city_id'] ?? null,
                            'min_people' => $validatedGrid['min_people'],
                            'max_people' => $validatedGrid['max_people'] ?? null,
                            'price' => $validatedGrid['price'],
                            'currency' => $validatedGrid['currency'],
                            'programme' => $validatedGrid['programme'] 
                        ]);
                    }
                }
            }

            // === Inclusions ===
            // supporte remplacement complet si on envoie 'inclusions_replace' à true (supprime+ajoute)
            if ($request->has('inclusions')) {
                if ($request->boolean('inclusions_replace', false)) {
                    $package->inclusions()->delete();
                }

                $inclusions = $request->input('inclusions');
                if ($this->isListOfStrings($inclusions)) {
                    foreach ($inclusions as $name) {
                        $package->inclusions()->create(['name' => $name]);
                    }
                } else {
                    foreach ($inclusions as $inc) {
                        $validatedInc = validator($inc, [
                            'name' => 'required|string|max:150',
                            'description' => 'nullable|string',
                        ])->validate();

                        $package->inclusions()->create($validatedInc);
                    }
                }
            }

            // === Additional cities ===
            if ($request->has('additional_cities')) {
                // option : replace all
                if ($request->boolean('additional_cities_replace', false)) {
                    $package->additionalCities()->detach();
                }
                $cities = $request->input('additional_cities');
                foreach ($cities as $c) {
                    if (is_array($c)) {
                        $cityId = $c['city_id'] ?? null;
                        $type = $c['type'] ?? null;
                    } else {
                        $cityId = $c;
                        $type = null;
                    }
                    if ($cityId) {
                        // avoid duplicate attach
                        if (!$package->additionalCities()->wherePivot('city_id', $cityId)->exists()) {
                            $package->additionalCities()->attach($cityId, ['type' => $type]);
                        }
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Package mis à jour avec succès',
                'data' => $package->load(['prices', 'inclusions', 'additionalCities'])
            ], 200);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => 'Erreur de validation', 'details' => $e->errors()], 422);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['error' => 'Package introuvable'], 404);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Erreur lors de la mise à jour du package', 'details' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $package = OuikenacPackage::findOrFail($id);
            $package->delete();
            return response()->json(['message' => 'Package supprimé avec succès'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Package introuvable'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Erreur lors de la suppression du package', 'details' => $e->getMessage()], 500);
        }
    }

    // helper pour détecter simple liste de strings
    private function isListOfStrings($arr)
    {
        if (!is_array($arr)) return false;
        foreach ($arr as $v) {
            if (!is_string($v)) return false;
        }
        return true;
    }
}
