<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    // Lister tous les packages, avec filtre par type ou pays
    public function index(Request $request)
    {
        $query = Package::with('prices');

        if ($request->has('package_type')) {
            $query->where('package_type', $request->package_type);
        }

        if ($request->has('country')) {
            $query->where('country', $request->country);
        }

        if ($request->has('sub_type')) {
            $query->where('sub_type', $request->sub_type);
        }

        $packages = $query->get();
        return response()->json($packages);
    }

    public function show($id)
    {
        $package = Package::with('prices')->findOrFail($id);
        return response()->json($package);
    }

    public function store(Request $request)
{
    $validated = $request->validate([
        'title' => 'required|string|max:100',
        'description' => 'nullable|string',
        'package_type' => 'required|in:ouikenac,destination,city_tour',
        'sub_type' => 'nullable|string|max:50',
        'country' => 'nullable|in:RC,RDC',
        'image' => 'nullable|file|max:5120',
        'prices' => 'array|nullable',
        'prices.*.country' => 'nullable|in:RC,RDC',
        'prices.*.min_people' => 'nullable|integer|min:1',
        'prices.*.max_people' => 'nullable|integer|min:1',
        'prices.*.price' => 'required_with:prices|numeric|min:0',
        'prices.*.currency' => 'nullable|string|max:10',
    ]);

    // 🖼️ Si une image est envoyée, on la stocke
    if ($request->hasFile('image')) {
        $path = $request->file('image')->store('packages', 'public');
        $validated['image'] = '/storage/' . $path; // chemin public
    }

    // 💾 Création du package
    $package = Package::create($validated);

    // 💰 Enregistrement des prix
    if (!empty($validated['prices'])) {
        foreach ($validated['prices'] as $price) {
            $package->prices()->create($price);
        }
    }

    return response()->json([
        'message' => 'Package créé avec succès !',
        'package' => $package->load('prices')
    ], 201);
}

 public function update(Request $request, $id)
    {
        $package = Package::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'image' => 'nullable|string|max:255',
            'package_type' => 'sometimes|in:standard,access,destination,city_tour',
            'sub_type' => 'nullable|string|max:50',
            'country' => 'nullable|in:RC,RDC',
        ]);

        $package->update($validated);

        return response()->json([
            'message' => 'Package mis à jour avec succès',
            'package' => $package
        ]);
    }

    // Supprimer un package
    public function destroy($id)
    {
        $package = Package::findOrFail($id);
        $package->delete();

        return response()->json(['message' => 'Package supprimé avec succès']);
    }

}
