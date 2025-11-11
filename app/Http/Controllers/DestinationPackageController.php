<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DestinationPackage;
use App\Models\PackagePrice;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use Illuminate\Support\Facades\DB;

class DestinationPackageController extends Controller
{
    /**
     * Liste tous les packages
     */
    public function index()
    {
        try {
            $packages = DestinationPackage::with(['departureCountry', 'arrivalCountry', 'prices', 'services'])->get();
            return response()->json($packages, 200);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Erreur lors du chargement des packages',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crée un package
     */
    public function store(Request $r)
    {
        try {
            $data = $r->validate([
                'title' => 'required|string|max:150',
                'description' => 'nullable|string',
                'image' => 'nullable|image',
                'departure_country_id' => 'required|exists:countries,id',
                'arrival_country_id' => 'nullable|exists:countries,id',
                'services' => 'array|nullable',
                'prices' => 'array|nullable',
            ]);

            if ($r->hasFile('image')) {
                $path = $r->file('image')->store('packages', 'public');
                $data['image'] = asset('storage/' . $path);
            }

            DB::beginTransaction();

            $pkg = DestinationPackage::create($data);

            if (!empty($data['services'])) {
                foreach ($data['services'] as $s) {
                    $pkg->services()->attach($s['service_id'], ['details' => $s['details'] ?? null]);
                }
            }

            if (!empty($data['prices'])) {
                foreach ($data['prices'] as $p) {
                    $pkg->prices()->create($p);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Package créé avec succès',
                'data' => $pkg->load('departureCountry', 'arrivalCountry', 'prices', 'services')
            ], 201);

        } catch (ValidationException $e) {
            return response()->json(['error' => 'Erreur de validation', 'details' => $e->errors()], 422);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Erreur interne', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Affiche un package spécifique
     */
    public function show($id)
    {
        try {
            $pkg = DestinationPackage::with('prices', 'services')->findOrFail($id);
            return response()->json($pkg, 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Package introuvable'], 404);

        } catch (Exception $e) {
            return response()->json(['error' => 'Erreur lors de la récupération du package', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Met à jour un package
     */
    public function update(Request $r, $id)
    {
        try {
            $pkg = DestinationPackage::findOrFail($id);

            $validated = $r->validate([
                'title' => 'sometimes|string|max:150',
                'description' => 'nullable|string',
                'image' => 'nullable|image',
                'min_people' => 'nullable|integer|min:1',
                'max_people' => 'nullable|integer|min:1',
                'departure_country_id' => 'nullable|exists:countries,id',
                'arrival_country_id' => 'nullable|exists:countries,id',
                'services' => 'array|nullable',
                'prices' => 'array|nullable',
            ]);

            if ($r->hasFile('image')) {
                $path = $r->file('image')->store('packages', 'public');
                $validated['image'] = asset('storage/' . $path);
            }

            DB::beginTransaction();

            $pkg->update($validated);

            // Mettre à jour les services
            if ($r->has('services')) {
                $syncServices = [];
                foreach ($r->services as $s) {
                    $syncServices[$s['service_id']] = ['details' => $s['details'] ?? null];
                }
                $pkg->services()->sync($syncServices);
            }

            // Mettre à jour les prices
            if ($r->has('prices')) {
                foreach ($r->prices as $p) {
                    if (isset($p['id'])) {
                        // Update existing price
                        $pkg->prices()->where('id', $p['id'])->update($p);
                    } else {
                        // Create new price
                        $pkg->prices()->create($p);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Package mis à jour avec succès',
                'data' => $pkg->load('departureCountry', 'arrivalCountry', 'prices', 'services')
            ], 200);

        } catch (ValidationException $e) {
            return response()->json(['error' => 'Erreur de validation', 'details' => $e->errors()], 422);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Package introuvable'], 404);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Erreur lors de la mise à jour', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Supprime un package
     */
    public function destroy($id)
    {
        try {
            $pkg = DestinationPackage::findOrFail($id);
            $pkg->delete();
            return response()->json(['message' => 'Package supprimé avec succès'], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Package introuvable'], 404);

        } catch (Exception $e) {
            return response()->json(['error' => 'Erreur lors de la suppression', 'details' => $e->getMessage()], 500);
        }
    }
}
