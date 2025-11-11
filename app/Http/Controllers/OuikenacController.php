<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OuikenacPackage;
use App\Models\Country;
use App\Models\City;

class OuikenacController extends Controller
{
    /**
     * Afficher tous les packages Ouikenac
     */
    public function index()
    {
        $OuikenacPackages = OuikenacPackage::with(['country_depart', 'country_arrivee', 'city_depart', 'city_arrivee'])->get();
        return response()->json($OuikenacPackages);
    }

    /**
     * Créer un nouveau package Ouikenac
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'image' => 'nullable|string',
            'services_inclus' => 'nullable|array',
            'repas' => 'boolean',
            'transport' => 'boolean',
            'hebergement' => 'boolean',
            'country_depart_id' => 'required|exists:countries,id',
            'country_arrivee_id' => 'required|exists:countries,id',
            'city_depart_id' => 'required|exists:cities,id',
            'city_arrivee_id' => 'required|exists:cities,id',
            'prix' => 'required|numeric|min:0',
            'places_min' => 'required|integer|min:1',
            'places_max' => 'required|integer|min:1',
            'programme' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $OuikenacPackage = OuikenacPackage::create($validated);
        return response()->json(['message' => 'Ouikenac créé avec succès', 'data' => $OuikenacPackage], 201);
    }

    /**
     * Afficher un package spécifique
     */
    public function show($id)
    {
        $OuikenacPackage = OuikenacPackage::with(['country_depart', 'country_arrivee', 'city_depart', 'city_arrivee'])->findOrFail($id);
        return response()->json($OuikenacPackage);
    }

    /**
     * Mettre à jour un package
     */
    public function update(Request $request, $id)
    {
        $OuikenacPackage = OuikenacPackage::findOrFail($id);

        $validated = $request->validate([
            'nom' => 'sometimes|string|max:255',
            'image' => 'nullable|string',
            'services_inclus' => 'nullable|array',
            'repas' => 'boolean',
            'transport' => 'boolean',
            'hebergement' => 'boolean',
            'country_depart_id' => 'sometimes|exists:countries,id',
            'country_arrivee_id' => 'sometimes|exists:countries,id',
            'city_depart_id' => 'sometimes|exists:cities,id',
            'city_arrivee_id' => 'sometimes|exists:cities,id',
            'prix' => 'sometimes|numeric|min:0',
            'places_min' => 'sometimes|integer|min:1',
            'places_max' => 'sometimes|integer|min:1',
            'programme' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $OuikenacPackage->update($validated);
        return response()->json(['message' => 'Ouikenac mis à jour avec succès', 'data' => $OuikenacPackage]);
    }

    /**
     * Supprimer un package
     */
    public function destroy($id)
    {
        $OuikenacPackage = OuikenacPackage::findOrFail($id);
        $OuikenacPackage->delete();
        return response()->json(['message' => 'Ouikenac supprimé avec succès']);
    }
}
