<?php

namespace App\Http\Controllers;

use App\Models\AccommodationRoom;
use App\Models\CityTour;
use App\Models\DestinationPackage;
use App\Models\OuikenacPackage;
use App\Models\PackagePrice;
use App\Models\Post;
use App\Models\Reservation;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Reservation::query();

            $query->with([
                'reservable' => function ($morph) {
                    $morph->morphWith([
                        CityTour::class => ['city', 'country', 'prices'],
                        DestinationPackage::class => ['prices', 'departureCountry'],
                        OuikenacPackage::class => ['prices', 'additionalCities', 'inclusions', 'country'],
                        AccommodationRoom::class => ['accommodation.city'],
                        Post::class => [],
                    ]);
                },
            ]);

            if ($request->filled('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%");
                });
            }

            $perPage = max(1, min((int) $request->query('per_page', 25), 100));
            $reservations = $query->orderByDesc('created_at')->paginate($perPage);

            return response()->json($reservations, 200);
        } catch (Exception $e) {
            Log::error('Reservation index error', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Erreur lors de la recuperation des reservations',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'reservable_id' => 'required|integer',
                'type' => 'required|string|in:city-tour,destination-package,ouikenac-package,accommodation_room,city-activity',
                'full_name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'required|string|max:20',
                'travelers' => 'required|integer|min:1',
                'message' => 'nullable|string|max:1000',
                'grid_id' => 'nullable|integer|exists:package_prices,id',
                'reservation_type' => 'nullable|string|in:individual,group',
                'date_reservation' => 'nullable|date',
                'date_from' => 'nullable|date|required_if:type,accommodation_room',
                'date_to' => 'nullable|date|after:date_from|required_if:type,accommodation_room',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Donnees invalides',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $data = $validator->validated();
            $travelers = (int) $data['travelers'];

            $modelClass = match ($data['type']) {
                'city-tour' => CityTour::class,
                'destination-package' => DestinationPackage::class,
                'ouikenac-package' => OuikenacPackage::class,
                'accommodation_room' => AccommodationRoom::class,
                'city-activity' => Post::class,
                default => null,
            };

            if (!$modelClass) {
                return response()->json(['message' => 'Type de reservation inconnu'], 400);
            }

            $reservableItem = $modelClass::find($data['reservable_id']);
            if (!$reservableItem) {
                return response()->json(['message' => 'Element introuvable'], 404);
            }

            $dateFromStr = $data['date_reservation'] ?? ($data['date_from'] ?? null);
            $dateToStr = $data['date_to'] ?? null;

            if (!$dateFromStr && isset($reservableItem->scheduled_date)) {
                $dateFromStr = $reservableItem->scheduled_date;
            }

            $dateFrom = $dateFromStr ? Carbon::parse($dateFromStr) : null;
            $dateTo = $dateToStr ? Carbon::parse($dateToStr) : null;

            if ($modelClass === AccommodationRoom::class && $dateFrom && $dateTo) {
                if (!$reservableItem->isAvailable($dateFrom, $dateTo)) {
                    return response()->json([
                        'status' => 'ERROR',
                        'message' => 'Cette chambre est indisponible pour la periode selectionnee.',
                        'error_code' => 'ROOM_UNAVAILABLE',
                    ], 422);
                }
            }

            $unitPrice = 0;
            $currency = 'CFA';
            $totalPrice = 0;
            $priceDetails = '';

            if ($modelClass === AccommodationRoom::class) {
                $unitPrice = $reservableItem->price;
                $currency = $reservableItem->currency ?? 'CFA';

                $nights = 1;
                if ($dateFrom && $dateTo) {
                    $nights = $dateFrom->diffInDays($dateTo);
                    if ($nights < 1) {
                        $nights = 1;
                    }
                }

                $totalPrice = (float) $unitPrice * $nights;
                $priceDetails = "[Hebergement: {$nights} nuit(s) a {$unitPrice} {$currency}/nuit]";
            } elseif ($modelClass === Post::class) {
                $meta = $this->extractActivityMetadata($reservableItem->content);
                if (!$meta || !$reservableItem->active) {
                    return response()->json(['message' => 'Cette activite est invalide ou non reservable.'], 422);
                }

                $reservedSpots = (int) $reservableItem->reservations()
                    ->whereIn('status', ['pending', 'approved'])
                    ->sum('travelers');

                $availableSpots = (int) $meta['available_spots'];
                if (($reservedSpots + $travelers) > $availableSpots) {
                    return response()->json([
                        'message' => 'Plus assez de places disponibles pour cette activite.',
                    ], 422);
                }

                $unitPrice = (float) ($meta['price'] ?? 0);
                $currency = $meta['currency'] ?? 'CFA';
                $totalPrice = $unitPrice * $travelers;
                $schedule = $meta['schedule'] ?? 'N/A';
                $departure = $meta['departure_point'] ?? 'N/A';
                $priceDetails = "[Activite: {$reservableItem->title} | Horaire: {$schedule} | Depart: {$departure}]";
            } elseif (!empty($data['grid_id'])) {
                $priceGrid = PackagePrice::find($data['grid_id']);

                if (!$priceGrid || (int) $priceGrid->priceable_id !== (int) $reservableItem->id || $priceGrid->priceable_type !== $modelClass) {
                    return response()->json(['message' => 'Tarif incompatible'], 400);
                }

                $typeRes = $data['reservation_type'] ?? 'individual';
                $currency = $priceGrid->currency ?? 'CFA';

                if ($typeRes === 'group') {
                    $groupPrice = $priceGrid->price_group ?? 0;
                    $groupSize = $priceGrid->min_people ?? 0;

                    if ($travelers > 0 && $travelers === (int) $groupSize) {
                        $totalPrice = (float) $groupPrice;
                        $priceDetails = "[Groupe fixe {$travelers} pax]";
                    } else {
                        $unitPrice = $priceGrid->price_individual ?? 0;
                        $totalPrice = (float) $unitPrice * $travelers;
                        $priceDetails = "[Groupe adapte {$travelers} pax]";
                    }
                } else {
                    $unitPrice = $priceGrid->price_individual ?? 0;
                    $totalPrice = (float) $unitPrice * $travelers;
                    $priceDetails = "[Individuel {$travelers} pax]";
                }
            } elseif (isset($reservableItem->price) && is_numeric($reservableItem->price)) {
                $unitPrice = $reservableItem->price;
                $currency = $reservableItem->currency ?? 'CFA';
                $totalPrice = (float) $unitPrice * $travelers;
                $priceDetails = "[Prix simple x {$travelers} pax]";
            }

            $reservation = $reservableItem->reservations()->create([
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'date_from' => $dateFrom ? $dateFrom->format('Y-m-d') : null,
                'date_to' => $dateTo ? $dateTo->format('Y-m-d') : null,
                'travelers' => $travelers,
                'total_price' => $totalPrice,
                'currency' => $currency,
                'message' => trim(($data['message'] ?? '') . ' ' . $priceDetails),
                'status' => 'pending',
            ]);

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Reservation enregistree avec succes',
                'data' => $reservation,
            ], 201);
        } catch (Exception $e) {
            Log::error('Reservation store error', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'ERROR',
                'message' => 'Erreur serveur lors de la reservation',
            ], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $actor = $request->user();
            if (!$actor || !in_array($actor->role, ['super_admin', 'manager'], true)) {
                return response()->json(['message' => 'Action non autorisee'], 403);
            }

            $reservation = Reservation::findOrFail($id);
            $validated = $request->validate([
                'status' => 'required|in:pending,approved,rejected,cancelled',
            ]);

            $reservation->status = $validated['status'];
            $reservation->validated_by = in_array($validated['status'], ['approved', 'rejected', 'cancelled'], true)
                ? $actor->id
                : null;
            $reservation->save();

            return response()->json([
                'message' => 'Statut mis a jour',
                'data' => $reservation,
            ], 200);
        } catch (Exception $e) {
            Log::error('Reservation status update error', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Erreur serveur'], 500);
        }
    }

    private function extractActivityMetadata(?string $content): ?array
    {
        if (!$content) {
            return null;
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return null;
        }

        if (($decoded['kind'] ?? null) !== 'city-activity') {
            return null;
        }

        $required = ['price', 'currency', 'available_spots', 'schedule', 'departure_point'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $decoded)) {
                return null;
            }
        }

        if (!is_numeric($decoded['price']) || !is_numeric($decoded['available_spots'])) {
            return null;
        }

        return $decoded;
    }
}
