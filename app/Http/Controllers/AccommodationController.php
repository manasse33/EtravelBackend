<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Accommodation;
use Illuminate\Support\Facades\DB;
use Exception;

class AccommodationController extends Controller
{
    public function index()
    {
        $data = Accommodation::with(['city.country', 'rooms'])
            ->where('active', true)
            ->latest()
            ->get();
        return response()->json(['data' => $data]);
    }

    public function show($id)
    {
        $accommodation = Accommodation::with(['city.country', 'rooms'])->find($id);
        if (!$accommodation) return response()->json(['message' => 'Hébergement non trouvé'], 404);
        return response()->json(['data' => $accommodation]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:hotel,apartment,guesthouse',
            'city_id' => 'required|exists:cities,id',
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'stars' => 'nullable|integer|min:0|max:5',
            'image' => 'nullable|image|max:5120',
            'gallery.*' => 'nullable|image|max:5120', // Validation pour chaque image de la galerie
            'rooms' => 'nullable'
        ]);

        try {
            DB::beginTransaction();

            $data = $request->except(['image', 'gallery', 'rooms']);

            // 1. IMAGE PRINCIPALE
            if ($request->hasFile('image')) {
                $imageFile = $request->file('image');
                $folderName = 'accommodations';
                $destinationPath = storage_path("app/public/{$folderName}");
                $fileName = $imageFile->hashName();

                if (!file_exists($destinationPath)) mkdir($destinationPath, 0775, true);
                
                if ($imageFile->move($destinationPath, $fileName)) {
                    $data['image'] = "{$folderName}/{$fileName}";
                }
            }

            // 2. GALERIE PHOTOS (NOUVEAU)
            $galleryPaths = [];
            if ($request->hasFile('gallery')) {
                $galleryFolder = 'accommodations/gallery';
                $galleryDest = storage_path("app/public/{$galleryFolder}");
                if (!file_exists($galleryDest)) mkdir($galleryDest, 0775, true);

                foreach($request->file('gallery') as $gFile) {
                    $gName = $gFile->hashName();
                    if ($gFile->move($galleryDest, $gName)) {
                        $galleryPaths[] = "{$galleryFolder}/{$gName}";
                    }
                }
            }
            // Le Model cast 'array' convertira ça en JSON automatiquement
            $data['gallery_images'] = $galleryPaths; 

            $accommodation = Accommodation::create($data);

            // 3. CHAMBRES
            if ($request->has('rooms')) {
                $this->syncRooms($accommodation, $request->input('rooms'), $request);
            }

            DB::commit();
            
            return response()->json([
                'message' => 'Hébergement créé avec succès', 
                'data' => $accommodation->load('rooms')
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $accommodation = Accommodation::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'image' => 'nullable|image|max:5120',
            'gallery.*' => 'nullable|image|max:5120',
        ]);

        try {
            DB::beginTransaction();

            $data = $request->except(['image', 'gallery', 'rooms']);

            // 1. IMAGE PRINCIPALE
            if ($request->hasFile('image')) {
                // Supprimer ancienne image
                if ($accommodation->image) {
                    @unlink(storage_path("app/public/{$accommodation->image}"));
                }
                $imageFile = $request->file('image');
                $folderName = 'accommodations';
                $destinationPath = storage_path("app/public/{$folderName}");
                $fileName = $imageFile->hashName();

                if (!file_exists($destinationPath)) mkdir($destinationPath, 0775, true);
                
                if ($imageFile->move($destinationPath, $fileName)) {
                    $data['image'] = "{$folderName}/{$fileName}";
                }
            }

            // 2. GALERIE PHOTOS - AJOUT (APPEND)
            // On récupère les images existantes pour ne pas les écraser
            $currentGallery = $accommodation->gallery_images ?? [];
            
            if ($request->hasFile('gallery')) {
                $galleryFolder = 'accommodations/gallery';
                $galleryDest = storage_path("app/public/{$galleryFolder}");
                if (!file_exists($galleryDest)) mkdir($galleryDest, 0775, true);

                foreach($request->file('gallery') as $gFile) {
                    $gName = $gFile->hashName();
                    if ($gFile->move($galleryDest, $gName)) {
                        // On ajoute la nouvelle image à la liste
                        $currentGallery[] = "{$galleryFolder}/{$gName}";
                    }
                }
            }
            // Mise à jour du tableau complet
            $data['gallery_images'] = $currentGallery;

            $accommodation->update($data);

            // 3. CHAMBRES
            if ($request->has('rooms')) {
                $accommodation->rooms()->delete(); 
                $this->syncRooms($accommodation, $request->input('rooms'), $request);
            }

            DB::commit();
            return response()->json(['message' => 'Mis à jour avec succès', 'data' => $accommodation->load('rooms')]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            // Le nettoyage des fichiers se fait dans le boot() du Model
            $accommodation = Accommodation::findOrFail($id);
            $accommodation->delete(); 
            
            return response()->json(['message' => 'Hébergement supprimé']);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Logique de synchronisation des chambres
     */
    private function syncRooms(Accommodation $accommodation, $roomsInput, Request $request)
    {
        $rooms = is_string($roomsInput) ? json_decode($roomsInput, true) : $roomsInput;

        if (!is_array($rooms)) return;

        foreach ($rooms as $index => $roomData) {
            $roomImgPath = $roomData['image'] ?? null;

            if ($request->hasFile("rooms.{$index}.image")) {
                $rFile = $request->file("rooms.{$index}.image");
                $rFolder = 'accommodation_rooms'; 
                $rDest = storage_path("app/public/{$rFolder}");
                $rName = $rFile->hashName();

                if (!file_exists($rDest)) mkdir($rDest, 0775, true);
                
                if ($rFile->move($rDest, $rName)) {
                    $roomImgPath = "{$rFolder}/{$rName}";
                }
            }

            $accommodation->rooms()->create([
                'title' => $roomData['title'],
                'description' => $roomData['description'] ?? null,
                'price' => $roomData['price'],
                'currency' => $roomData['currency'] ?? 'CFA',
                'capacity_adults' => $roomData['capacity_adults'] ?? 2,
                'capacity_children' => $roomData['capacity_children'] ?? 0,
                'available_qty' => $roomData['available_qty'] ?? 1,
                'image' => $roomImgPath
            ]);
        }
    }
}