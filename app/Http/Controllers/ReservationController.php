<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Reservation;
use App\Models\CityTour;
use App\Models\DestinationPackage;
use App\Models\OuikenacPackage;
use App\Models\PackagePrice; // Assurez-vous d'avoir ce modèle
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Exception;

class ReservationController extends Controller
{
    /**
     * Lister toutes les réservations avec les relations nécessaires
     */
    public function index(Request $request)
    {
        try {
            $query = Reservation::query();

            // Chargement intelligent des relations selon le type de package
            $query->with([
                'reservable' => function ($morph) {
                    $morph->morphWith([
                        CityTour::class => ['city', 'country', 'prices'],
                        DestinationPackage::class => ['prices', 'departureCountry'],
                        // Ajout de 'country' ici pour distinguer RC/RDC dans l'admin
                        OuikenacPackage::class => ['prices', 'additionalCities', 'inclusions', 'country'],
                    ]);
                }
            ]);

            // Filtre par statut si demandé
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // Recherche par nom ou email (Bonus)
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('id', 'like', "%{$search}%");
                });
            }

            $reservations = $query->orderBy('created_at', 'desc')->get();

            return response()->json($reservations, 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des réservations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer une réservation
     */
   public function store(Request $request)
    {
        try {
            // 1. Validation des données entrantes
            $validator = Validator::make($request->all(), [
                'reservable_id' => 'required|integer',
                'type' => 'required|string|in:city-tour,destination-package,ouikenac-package',
                'full_name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'required|string|max:20',
                'travelers' => 'required|integer|min:1',
                'grid_id' => 'nullable|integer|exists:package_prices,id', // Validation SQL directe
                'reservation_type' => 'nullable|string|in:individual,group', // Pour CityTour/Ouikenac
            ]);

            if ($validator->fails()) {
                return response()->json(['message' => 'Données invalides', 'errors' => $validator->errors()], 422);
            }

            $data = $request->all();

            // 2. Identification du Modèle
            $modelClass = match($data['type']) {
                'city-tour' => CityTour::class,
                'destination-package' => DestinationPackage::class,
                'ouikenac-package' => OuikenacPackage::class,
                default => null
            };

            if (!$modelClass) {
                return response()->json(['message' => "Type de package inconnu"], 400);
            }

            // 3. Récupération du Package
            $package = $modelClass::find($data['reservable_id']);
            if (!$package) {
                return response()->json(['message' => "Package introuvable"], 404);
            }

            // =========================================================
            // 4. CALCUL DU PRIX (LOGIQUE PRO)
            // =========================================================
            $unitPrice = 0;
            $currency = 'CFA';
            $totalPrice = 0; // Le résultat final de notre calcul
            $travelers = intval($data['travelers']); // Nombre de personnes de la réservation
            $priceDetails = ""; 
            
            // CAS A : Utilisation d'une grille tarifaire (Pour Ouikenac/City-Tour)
            if (!empty($data['grid_id'])) {
                $priceGrid = PackagePrice::find($data['grid_id']);

                // Sécurité : Vérifier que ce prix appartient bien au package demandé
                if (!$priceGrid || $priceGrid->priceable_id != $package->id || $priceGrid->priceable_type !== $modelClass) {
                     return response()->json(['message' => "Le tarif sélectionné ne correspond pas au package"], 400);
                }
                
                $typeRes = $data['reservation_type'] ?? 'individual';
                $currency = $priceGrid->currency ?? 'CFA';
                
                // Si l'utilisateur a sélectionné un forfait GROUPE
                if ($typeRes === 'group') {
                    $groupPrice = $priceGrid->price_group ?? 0;
                    // Utilisation de 'min_people' comme taille de groupe attendue
                    $groupSize = $priceGrid->min_people ?? 0; 
                    
                    // --- CAS 1 : Les voyageurs correspondent au forfait de groupe (Prix Fixe) ---
                    if ($travelers > 0 && $travelers == $groupSize) {
                        $totalPrice = (float)$groupPrice; 
                        $priceDetails = "[Forfait Groupe Fixe pour {$travelers} pax (Grille #{$priceGrid->id})]";
                    } 
                    // --- CAS 3 : Les voyageurs ne correspondent PAS (Adaptation au prix individuel) ---
                    // Logique : Si le nombre ne correspond pas, on revient à la tarification par tête.
                    else {
                        $unitPrice = $priceGrid->price_individual ?? 0;
                        $totalPrice = (float)$unitPrice * $travelers; 
                        $priceDetails = "[Groupe Adapté: Prix Individuel x {$travelers} pax (Grille #{$priceGrid->id})]";
                    }
                } 
                // --- CAS 2 : Forfait INDIVIDUEL ---
                else {
                    $unitPrice = $priceGrid->price_individual ?? 0;
                    $totalPrice = (float)$unitPrice * $travelers; // Multiplication simple
                    $priceDetails = "[Prix Individuel Standard x {$travelers} pax (Grille #{$priceGrid->id})]";
                }

            } 
            // CAS B : Prix simple sur le modèle (Pour DestinationPackage)
            elseif (isset($package->price) && is_numeric($package->price)) {
                $unitPrice = $package->price;
                $currency = $package->currency ?? 'CFA';
                
                // DestinationPackage : toujours Prix unitaire x Nombre de voyageurs
                $totalPrice = (float)$unitPrice * $travelers; 
                $priceDetails = "[Prix Simple Destination x {$travelers} pax]";
            }
            
            // Sécurité : empêcher une réservation à 0 si le prix est attendu
            if ($totalPrice <= 0 && $travelers > 0) {
                 return response()->json(['message' => "Impossible de calculer un prix valide pour cette sélection. Vérifiez le package ou la grille tarifaire."], 400);
            }
            // =========================================================


            // 5. Gestion de la Date
            // On accepte 'date_reservation' ou 'date_from'
            $dateFrom = $data['date_reservation'] ?? ($data['date_from'] ?? null);
            
            if (!$dateFrom && isset($package->scheduled_date)) {
                $dateFrom = $package->scheduled_date;
            }

            // 6. Enregistrement
            $reservation = $package->reservations()->create([
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'date_from' => $dateFrom,
                'date_to' => $data['date_to'] ?? null, 
                'travelers' => $travelers,
                'total_price' => $totalPrice, 
                'currency' => $currency,
                'message' => ($data['message'] ?? '') . " " . $priceDetails,
                'status' => 'pending'
            ]);

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Réservation enregistrée avec succès',
                'data' => $reservation
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Erreur serveur lors de la réservation',
                'debug' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour le statut (Admin)
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $reservation = Reservation::findOrFail($id);

            $validated = $request->validate([
                'status' => 'required|in:pending,approved,rejected,cancelled'
            ]);

            $reservation->status = $validated['status'];
            // Si vous utilisez l'authentification admin :
            $reservation->validated_by = $request->user()?->id ?? null;
            $reservation->save();

            return response()->json([
                'message' => 'Statut mis à jour',
                'data' => $reservation
            ], 200);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['message' => 'Erreur serveur', 'error' => $e->getMessage()], 500);
        }
    }
}