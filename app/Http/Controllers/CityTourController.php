<?php

namespace App\Http\Controllers;

use App\Models\CityTour;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;

class CityTourController extends Controller
{
    private const IMAGE_MAX_KB = 5120;
    private const IMAGE_FOLDER = 'city_tours';

    /**
     * Lister tous les City Tours.
     */
    public function index()
    {
        $tours = CityTour::with(['country', 'city', 'prices', 'inclusions'])
            ->latest()
            ->get();

        return response()->json($tours);
    }

    /**
     * Creer un City Tour.
     */
    public function store(Request $request)
    {
        try {
            $this->prepareOptionalImageUpload($request, 'image');

            $validated = $request->validate([
                'nom' => 'required|string|max:255',
                'country_id' => 'required|exists:countries,id',
                'city_id' => 'required|exists:cities,id',
                'date' => 'required|date',
                'places_min' => 'required|integer|min:1',
                'places_max' => 'required|integer|min:1|gte:places_min',
                'description' => 'nullable|string',
                'itinerary' => 'nullable|string',
                'programme' => 'nullable|string',
                'price_individual' => 'nullable',
                'price_group' => 'nullable',
                'currency' => 'required|in:CFA,USD,EUR',
                'image' => 'nullable|file|image|max:' . self::IMAGE_MAX_KB,
            ]);

            DB::beginTransaction();

            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $this->storeImage($request->file('image'));
            }

            $tour = CityTour::create([
                'title' => $validated['nom'],
                'country_id' => $validated['country_id'],
                'city_id' => $validated['city_id'],
                'scheduled_date' => $validated['date'],
                'min_people' => $validated['places_min'],
                'max_people' => $validated['places_max'],
                'description' => $validated['description'] ?? null,
                'itinerary' => $validated['itinerary'] ?? null,
                'image' => $imagePath,
                'active' => true,
            ]);

            $priceIndividual = $this->nullableInput($validated['price_individual'] ?? null);
            $priceGroup = $this->nullableInput($validated['price_group'] ?? null);
            $mainPrice = $priceGroup ?? $priceIndividual ?? 0;

            $tour->prices()->create([
                'price' => $mainPrice,
                'price_individual' => $priceIndividual,
                'price_group' => $priceGroup,
                'currency' => $validated['currency'],
                'min_people' => $validated['places_min'],
                'max_people' => $validated['places_max'],
                'programme' => $validated['programme'] ?? null,
            ]);

            if ($request->has('inclusions')) {
                $this->syncInclusions($tour, $request->input('inclusions'));
            }

            DB::commit();

            return response()->json([
                'message' => 'City Tour cree avec succes',
                'data' => $tour->load(['prices', 'inclusions']),
            ], 201);
        } catch (ValidationException $e) {
            $this->rollBackIfNeeded();

            return response()->json(['message' => 'Erreur de validation', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            $this->rollBackIfNeeded();
            \Log::error('Erreur Store CityTour: ' . $e->getMessage());

            return response()->json(['message' => 'Erreur serveur lors de la creation.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Afficher un City Tour specifique.
     */
    public function show($id)
    {
        try {
            $tour = CityTour::with(['country', 'city', 'prices', 'inclusions'])->findOrFail($id);

            return response()->json($tour);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'City Tour introuvable'], 404);
        }
    }

    /**
     * Mettre a jour un City Tour.
     */
    public function update(Request $request, $id)
    {
        try {
            $tour = CityTour::findOrFail($id);
            $this->prepareOptionalImageUpload($request, 'image');

            $validated = $request->validate([
                'nom' => 'sometimes|string|max:255',
                'country_id' => 'sometimes|exists:countries,id',
                'city_id' => 'sometimes|exists:cities,id',
                'date' => 'sometimes|date',
                'places_min' => 'sometimes|integer|min:1',
                'places_max' => 'sometimes|integer|min:1|gte:places_min',
                'description' => 'nullable|string',
                'itinerary' => 'nullable|string',
                'programme' => 'nullable|string',
                'price_individual' => 'nullable',
                'price_group' => 'nullable',
                'currency' => 'sometimes|in:CFA,USD,EUR',
                'image' => 'nullable|file|image|max:' . self::IMAGE_MAX_KB,
                'active' => 'boolean',
            ]);

            DB::beginTransaction();

            if ($request->hasFile('image')) {
                if ($tour->image) {
                    $oldPath = storage_path("app/public/{$tour->image}");
                    if (file_exists($oldPath)) {
                        @unlink($oldPath);
                    }
                }

                $tour->image = $this->storeImage($request->file('image'));
            }

            if (isset($validated['nom'])) {
                $tour->title = $validated['nom'];
            }
            if (isset($validated['country_id'])) {
                $tour->country_id = $validated['country_id'];
            }
            if (isset($validated['city_id'])) {
                $tour->city_id = $validated['city_id'];
            }
            if (isset($validated['date'])) {
                $tour->scheduled_date = $validated['date'];
            }
            if (isset($validated['places_min'])) {
                $tour->min_people = $validated['places_min'];
            }
            if (isset($validated['places_max'])) {
                $tour->max_people = $validated['places_max'];
            }
            if (array_key_exists('description', $validated)) {
                $tour->description = $validated['description'];
            }
            if (array_key_exists('itinerary', $validated)) {
                $tour->itinerary = $validated['itinerary'];
            }
            if (array_key_exists('active', $validated)) {
                $tour->active = $validated['active'];
            }

            $tour->save();

            $price = $tour->prices()->firstOrNew([]);

            if (isset($validated['currency'])) {
                $price->currency = $validated['currency'];
            }
            if (isset($validated['places_min'])) {
                $price->min_people = $validated['places_min'];
            }
            if (isset($validated['places_max'])) {
                $price->max_people = $validated['places_max'];
            }
            if (array_key_exists('programme', $validated)) {
                $price->programme = $validated['programme'];
            }
            if (array_key_exists('price_individual', $validated)) {
                $price->price_individual = $this->nullableInput($validated['price_individual']);
            }
            if (array_key_exists('price_group', $validated)) {
                $price->price_group = $this->nullableInput($validated['price_group']);
            }

            $price->price = $price->price_group ?? $price->price_individual ?? $price->price ?? 0;
            $price->save();

            if ($request->has('inclusions')) {
                $this->syncInclusions($tour, $request->input('inclusions'));
            }

            DB::commit();

            return response()->json([
                'message' => 'City Tour mis a jour avec succes',
                'data' => $tour->load(['prices', 'inclusions']),
            ], 200);
        } catch (ValidationException $e) {
            $this->rollBackIfNeeded();

            return response()->json(['message' => 'Erreur de validation', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            $this->rollBackIfNeeded();
            \Log::error('Erreur Update CityTour: ' . $e->getMessage());

            return response()->json(['message' => 'Erreur serveur lors de la mise a jour.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Supprimer un City Tour.
     */
    public function destroy($id)
    {
        try {
            $tour = CityTour::findOrFail($id);

            if ($tour->image) {
                $filePath = storage_path("app/public/{$tour->image}");
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }

            $tour->delete();

            return response()->json(['message' => 'City Tour supprime avec succes']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'City Tour introuvable'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'Erreur serveur', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint public de diagnostic pour verifier rapidement un upload image.
     */
    public function storeImageTest(Request $request)
    {
        try {
            $this->prepareOptionalImageUpload($request, 'image');

            $request->validate([
                'image' => 'required|file|image|max:' . self::IMAGE_MAX_KB,
            ]);

            $path = $this->storeImage($request->file('image'));

            return response()->json([
                'message' => 'Image uploadee avec succes',
                'path' => $path,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Erreur de validation', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            \Log::error('Erreur Test Image CityTour: ' . $e->getMessage());

            return response()->json(['message' => 'Erreur serveur lors de l upload.', 'error' => $e->getMessage()], 500);
        }
    }

    private function prepareOptionalImageUpload(Request $request, string $field): void
    {
        $file = $request->files->get($field);

        if (!$file instanceof SymfonyUploadedFile) {
            return;
        }

        if ($file->getError() === UPLOAD_ERR_NO_FILE) {
            $request->files->remove($field);
            return;
        }

        if (!$file->isValid()) {
            throw ValidationException::withMessages([
                $field => [$this->uploadErrorMessage($file->getError())],
            ]);
        }
    }

    private function storeImage(UploadedFile $imageFile): string
    {
        $destinationPath = storage_path('app/public/' . self::IMAGE_FOLDER);
        $fileName = $imageFile->hashName();

        if (!file_exists($destinationPath) && !mkdir($destinationPath, 0775, true)) {
            throw new Exception("Echec de creation du dossier: {$destinationPath}");
        }

        if (!$imageFile->move($destinationPath, $fileName)) {
            throw new Exception("Echec du deplacement de l'image.");
        }

        return self::IMAGE_FOLDER . "/{$fileName}";
    }

    private function nullableInput(mixed $value): mixed
    {
        return ($value !== '' && $value !== null) ? $value : null;
    }

    private function syncInclusions(CityTour $tour, mixed $input): void
    {
        $tour->inclusions()->delete();

        foreach ($this->normalizeInclusions($input) as $inclusion) {
            $tour->inclusions()->create($inclusion);
        }
    }

    private function normalizeInclusions(mixed $input): array
    {
        if (is_string($input)) {
            $decoded = json_decode($input, true);
            $input = json_last_error() === JSON_ERROR_NONE ? $decoded : [$input];
        }

        if (!is_array($input)) {
            return [];
        }

        $inclusions = [];

        foreach ($input as $item) {
            if (is_string($item)) {
                $name = trim($item);
                $description = null;
            } elseif (is_array($item)) {
                $name = trim((string)($item['name'] ?? ''));
                $description = $item['description'] ?? null;
            } else {
                continue;
            }

            if ($name !== '') {
                $inclusions[] = [
                    'name' => $name,
                    'description' => $description,
                ];
            }
        }

        return $inclusions;
    }

    private function uploadErrorMessage(int $errorCode): string
    {
        $maxMb = self::IMAGE_MAX_KB / 1024;

        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "L'image depasse la limite d'upload autorisee. Taille maximale attendue: {$maxMb} Mo. Verifiez aussi upload_max_filesize et post_max_size dans PHP.",
            UPLOAD_ERR_PARTIAL => "L'image n'a ete envoyee que partiellement. Veuillez relancer l'upload.",
            UPLOAD_ERR_NO_TMP_DIR => "Le dossier temporaire d'upload PHP est manquant sur le serveur.",
            UPLOAD_ERR_CANT_WRITE => "Le serveur n'a pas pu ecrire le fichier upload dans le dossier temporaire.",
            UPLOAD_ERR_EXTENSION => "Une extension PHP a bloque l'upload de l'image.",
            default => "L'image n'a pas pu etre uploadee. Code erreur PHP: {$errorCode}.",
        };
    }

    private function rollBackIfNeeded(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
}
