<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DestinationPackage;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Exception;

class DestinationPackageController extends Controller
{
    /**
     * Liste tous les packages
     */
    public function index()
    {
        try {
            $packages = DestinationPackage::with(['departureCountry', 'prices'])
                ->latest()
                ->get();
                
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
                'image' => 'nullable|image|max:5120',
                'departure_country_id' => 'required|exists:countries,id',
                'price' => 'required|numeric|min:0',
                'currency' => 'nullable|in:CFA,USD,EUR',
            ]);

            DB::beginTransaction();

            // --- GESTION IMAGE MANUELLE (Solution Compatible) ---
            $imagePath = null;
            if ($r->hasFile('image')) {
                $imageFile = $r->file('image');
                $folderName = 'packages'; // Dossier de destination
                // Chemin physique absolu
                $destinationPath = storage_path("app/public/{$folderName}");
                $fileName = $imageFile->hashName();

                // 1. Création forcée du dossier avec permissions
                if (!file_exists($destinationPath)) {
                    if (!mkdir($destinationPath, 0775, true)) {
                        throw new Exception("Impossible de créer le dossier {$destinationPath}");
                    }
                }

                // 2. Déplacement du fichier
                if ($imageFile->move($destinationPath, $fileName)) {
                    $imagePath = "{$folderName}/{$fileName}";
                } else {
                    throw new Exception("Échec de l'upload de l'image");
                }
            }
            // ----------------------------------------------------

            // Création du package
            $pkg = DestinationPackage::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'image' => $imagePath,
                'departure_country_id' => $data['departure_country_id'],
                'active' => true
            ]);

            // Création du prix associé
            $pkg->prices()->create([
                'price' => $data['price'],
                'currency' => $data['currency'] ?? 'CFA',
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Package créé avec succès',
                'data' => $pkg->load('departureCountry', 'prices')
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => 'Erreur de validation', 'details' => $e->errors()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            \Log::error('Erreur Store DestinationPackage: ' . $e->getMessage());
            return response()->json(['error' => 'Erreur interne', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Affiche un package spécifique
     */
    public function show($id)
    {
        try {
            $pkg = DestinationPackage::with(['departureCountry', 'prices'])->findOrFail($id);
            return response()->json($pkg, 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Package introuvable'], 404);
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
                'image' => 'nullable|image|max:5120',
                'departure_country_id' => 'nullable|exists:countries,id',
                'price' => 'nullable|numeric|min:0', 
                'currency' => 'nullable|in:CFA,USD,EUR',
            ]);

            DB::beginTransaction();

            // --- GESTION IMAGE MANUELLE (Mise à jour) ---
            if ($r->hasFile('image')) {
                // 1. Suppression de l'ancienne image si elle existe
                if ($pkg->image) {
                    $oldPath = storage_path("app/public/{$pkg->image}");
                    if (file_exists($oldPath)) {
                        @unlink($oldPath); // Suppression physique
                    }
                }
                
                // 2. Upload de la nouvelle
                $imageFile = $r->file('image');
                $folderName = 'packages';
                $destinationPath = storage_path("app/public/{$folderName}");
                $fileName = $imageFile->hashName();

                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0775, true);
                }
                
                if ($imageFile->move($destinationPath, $fileName)) {
                    $pkg->image = "{$folderName}/{$fileName}";
                }
            }
            // ---------------------------------------------

            // 2. Mise à jour des champs simples
            if (isset($validated['title'])) $pkg->title = $validated['title'];
            if (isset($validated['description'])) $pkg->description = $validated['description'];
            if (isset($validated['departure_country_id'])) $pkg->departure_country_id = $validated['departure_country_id'];
            
            $pkg->save();

            // 3. Mise à jour du Prix
            $price = $pkg->prices()->firstOrNew([]);
            
            if (isset($validated['price'])) $price->price = $validated['price'];
            if (isset($validated['currency'])) $price->currency = $validated['currency'];
            
            if (!$price->exists) {
                $price->currency = $validated['currency'] ?? 'CFA';
                $price->price = $validated['price'] ?? 0;
            }
            
            $price->save();

            DB::commit();

            return response()->json([
                'message' => 'Package mis à jour avec succès',
                'data' => $pkg->load('departureCountry', 'prices')
            ], 200);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['error' => 'Erreur de validation', 'details' => $e->errors()], 422);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['error' => 'Package introuvable'], 404);

        } catch (Exception $e) {
            DB::rollBack();
            \Log::error('Erreur Update DestinationPackage: ' . $e->getMessage());
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
            
            // Suppression manuelle de l'image
            if ($pkg->image) {
                $filePath = storage_path("app/public/{$pkg->image}");
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }

            $pkg->delete();
            return response()->json(['message' => 'Package supprimé avec succès'], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Package introuvable'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Erreur lors de la suppression', 'details' => $e->getMessage()], 500);
        }
    }
}