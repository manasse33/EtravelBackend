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
            $packages = DestinationPackage::with(['departureCountry',  'prices'])->get();
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
                'image' => 'nullable|image|max:5120', // Validation du fichier image
                'departure_country_id' => 'required|exists:countries,id',
                'price' => 'required|numeric|min:0',
                'currency' => 'nullable|in:CFA,USD,EUR',
            ]);

            DB::beginTransaction();

            // ✅ SOLUTION DE CONTOURNEMENT INTÉGRÉE : Utilisation de move()
            if ($r->hasFile('image')) {
                /** @var UploadedFile $imageFile */
                $imageFile = $r->file('image');
                $folderName = 'packages'; // Le dossier cible dans storage/app/public/
                $destinationPath = storage_path("app/public/{$folderName}");
                $fileName = $imageFile->hashName(); // Nom de fichier aléatoire sécurisé

                // 1. Assurez-vous que le dossier de destination existe
                if (!file_exists($destinationPath)) {
                    // Tente de créer le dossier récursivement
                    if (!mkdir($destinationPath, 0775, true)) {
                         // Si la création échoue (permission), on lève une erreur
                         throw new \Exception("Échec de création du dossier de destination: {$destinationPath}");
                    }
                }
                
                // 2. Déplacement du fichier avec la méthode move()
                if ($imageFile->move($destinationPath, $fileName)) {
                    // Le déplacement a réussi. Stocke le chemin relatif.
                    $data['image'] = "{$folderName}/{$fileName}";
                } else {
                    // Le déplacement a échoué (problème critique de serveur).
                    DB::rollBack();
                    throw new \Exception("Échec critique du déplacement du fichier image du Package de Destination.");
                }
            } else {
                 $data['image'] = null;
            }

            // Création du package
            $pkg = DestinationPackage::create($data);

            // Création du price associé
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
            // L'erreur catchée sera l'erreur explicite si le move() a échoué
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
                'departure_country_id' => 'nullable|exists:countries,id',
                'price' => 'required|numeric|min:0',
            ]);

            // if ($r->hasFile('image')) {
            //     $path = $r->file('image')->store('packages', 'public');
            //     $validated['image'] = asset('storage/' . $path);
            // }

            DB::beginTransaction();
             if ($r->hasFile('image')) {
                /** @var UploadedFile $imageFile */
                $imageFile = $r->file('image');
                $folderName = 'packages'; // Le dossier cible dans storage/app/public/
                $destinationPath = storage_path("app/public/{$folderName}");
                $fileName = $imageFile->hashName(); // Nom de fichier aléatoire sécurisé

                // 1. Assurez-vous que le dossier de destination existe
                if (!file_exists($destinationPath)) {
                    // Tente de créer le dossier récursivement
                    if (!mkdir($destinationPath, 0775, true)) {
                         // Si la création échoue (permission), on lève une erreur
                         throw new \Exception("Échec de création du dossier de destination: {$destinationPath}");
                    }
                }
                
                // 2. Déplacement du fichier avec la méthode move()
                if ($imageFile->move($destinationPath, $fileName)) {
                    // Le déplacement a réussi. Stocke le chemin relatif.
                    $data['image'] = "{$folderName}/{$fileName}";
                } else {
                    // Le déplacement a échoué (problème critique de serveur).
                    DB::rollBack();
                    throw new \Exception("Échec critique du déplacement du fichier image du Package de Destination.");
                }
            } else {
                 $data['image'] = null;
            }

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
                'data' => $pkg->load('departureCountry', 'prices')
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
