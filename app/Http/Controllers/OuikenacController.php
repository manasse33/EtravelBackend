<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OuikenacPackage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Exception;
use Illuminate\Support\Facades\DB;

class OuikenacController extends Controller
{
    public function index()
    {
        try {
            $packages = OuikenacPackage::with(['departureCountry', 'arrivalCountry','prices'])->get();
            return response()->json($packages, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Erreur lors du chargement des packages', 'details' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'image' => 'nullable|file',
                'departure_country_id' => 'required|exists:countries,id',
                'arrival_country_id' => 'nullable|exists:countries,id',
                'departure_city_id' => 'required|exists:cities,id',
                'arrival_city_id' => 'nullable|exists:cities,id',
                'min_people' => 'required|integer|min:1',
                'max_people' => 'required|integer|min:1',
                'active' => 'boolean',
                'price' => 'required|numeric|min:0',
            ]);

            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('packages', 'public');
                $validated['image'] = $path;
            }

            DB::beginTransaction();

            $package = OuikenacPackage::create($validated);

            $package->prices()->create([
                'price' => $validated['price'],
                'currency' => 'CFA',
                'min_people' => $validated['min_people'],
                'max_people' => $validated['max_people'],
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Package créé avec succès',
                'data' => $package->load('prices')
            ], 201);

        } catch (ValidationException $e) {
            return response()->json(['error' => 'Erreur de validation', 'details' => $e->errors()], 422);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Erreur lors de la création du package', 'details' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $package = OuikenacPackage::with(['departureCountry', 'arrivalCountry', 'prices'])->findOrFail($id);
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

            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'image' => 'nullable|file',
                'departure_country_id' => 'sometimes|exists:countries,id',
                'arrival_country_id' => 'sometimes|exists:countries,id',
                'departure_city_id' => 'sometimes|exists:cities,id',
                'arrival_city_id' => 'sometimes|exists:cities,id',
                'min_people' => 'sometimes|integer|min:1',
                'max_people' => 'sometimes|integer|min:1',
                'active' => 'boolean',
                'price' => 'sometimes|numeric|min:0',
            ]);

            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('packages', 'public');
                $validated['image'] = $path;
            }

            DB::beginTransaction();

            $package->update($validated);

            if ($request->has('price')) {
                $package->prices()->updateOrCreate(
                    ['package_id' => $package->id],
                    [
                        'price' => $validated['price'],
                        'currency' => 'CFA',
                        'min_people' => $validated['min_people'] ?? $package->min_people,
                        'max_people' => $validated['max_people'] ?? $package->max_people,
                    ]
                );
            }

            DB::commit();

            return response()->json(['message' => 'Package mis à jour avec succès', 'data' => $package->load('prices')], 200);

        } catch (ValidationException $e) {
            return response()->json(['error' => 'Erreur de validation', 'details' => $e->errors()], 422);

        } catch (ModelNotFoundException $e) {
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
}
