<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DestinationPackage;
use App\Models\PackagePrice;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class DestinationPackageController extends Controller
{
    /**
     * Liste de tous les packages
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
     * Création d’un package
     */
    public function store(Request $r)
    {
        try {
            $data = $r->validate([
                'title' => 'required|string',
                'description' => 'nullable|string',
                'image' => 'nullable|string',
                'departure_country_id' => 'required|exists:countries,id',
                'arrival_country_id' => 'required|exists:countries,id',
                'services' => 'array|nullable',
                'prices' => 'array|nullable'
            ]);

            $pkg = DestinationPackage::create($data);

            // Liaison des services
            if (!empty($data['services'])) {
                foreach ($data['services'] as $s) {
                    $pkg->services()->attach($s['service_id'], [
                        'details' => $s['details'] ?? null
                    ]);
                }
            }

            // Liaison des prix
            if (!empty($data['prices'])) {
                foreach ($data['prices'] as $p) {
                    $pkg->prices()->create($p);
                }
            }

            return response()->json([
                'message' => 'Package créé avec succès',
                'data' => $pkg->load('prices', 'services')
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Erreur de validation',
                'details' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la création du package',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Affichage d’un package spécifique
     */
    public function show($id)
    {
        try {
            $pkg = DestinationPackage::with('prices', 'services')->findOrFail($id);
            return response()->json($pkg, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Package introuvable'], 404);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la récupération du package',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mise à jour d’un package
     */
    public function update(Request $r, $id)
    {
        try {
            $pkg = DestinationPackage::findOrFail($id);

            $validated = $r->validate([
                'title' => 'sometimes|string',
                'description' => 'nullable|string',
                'image' => 'nullable|string',
                'min_people' => 'nullable|integer|min:1',
                'max_people' => 'nullable|integer|min:1',
                'departure_city_id' => 'nullable|exists:cities,id',
                'arrival_city_id' => 'nullable|exists:cities,id'
            ]);

            $pkg->update($validated);

            return response()->json([
                'message' => 'Package mis à jour avec succès',
                'data' => $pkg
            ], 200);

        } catch (ValidationException $e) {
            return response()->json(['error' => 'Erreur de validation', 'details' => $e->errors()], 422);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Package introuvable'], 404);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la mise à jour du package',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Suppression d’un package
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
            return response()->json([
                'error' => 'Erreur lors de la suppression du package',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
