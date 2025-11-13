<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Country;
use App\Models\City;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class ConfigController extends Controller
{
    // ------------------
    // PAYS
    // ------------------

    // GET /countries → liste tous les pays avec leurs villes
    public function indexCountries()
    {
        return response()->json(Country::with('cities')->get(), 200);
    }

    // POST /countries → créer un pays
    public function storeCountry(Request $r)
    {
        $data = $r->validate([
            'code' => 'required|string|max:10|unique:countries,code',
            'name' => 'required|string|max:150',
        ]);

        return response()->json(Country::create($data), 201);
    }

    // GET /countries/{id} → récupérer un pays
    public function showCountry($id)
    {
        try {
            $country = Country::with('cities')->findOrFail($id);
            return response()->json($country, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Pays introuvable'], 404);
        }
    }

    // PUT/PATCH /countries/{id} → modifier un pays
    public function updateCountry(Request $r, $id)
    {
        try {
            $country = Country::findOrFail($id);
            $data = $r->validate([
                'code' => 'sometimes|string|max:10|unique:countries,code,' . $id,
                'name' => 'sometimes|string|max:150',
            ]);
            $country->update($data);
            return response()->json($country, 200);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Erreur de validation', 'details' => $e->errors()], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Pays introuvable'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Erreur interne', 'details' => $e->getMessage()], 500);
        }
    }

    // DELETE /countries/{id} → supprimer un pays
    public function destroyCountry($id)
    {
        try {
            $country = Country::findOrFail($id);
            $country->delete();
            return response()->json(['message' => 'Pays supprimé avec succès'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Pays introuvable'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Erreur interne', 'details' => $e->getMessage()], 500);
        }
    }

    // ------------------
    // VILLES
    // ------------------

    // GET /cities → liste toutes les villes
    public function indexCities()
    {
        return response()->json(City::with('country')->get(), 200);
    }

    // POST /cities → créer une ville
    public function storeCity(Request $r)
    {
        $data = $r->validate([
            'country_id' => 'required|exists:countries,id',
            'name' => 'required|string|max:150',
        ]);

        return response()->json(City::create($data), 201);
    }

    // GET /cities/{id} → récupérer une ville
    public function showCity($id)
    {
        try {
            $city = City::with('country')->findOrFail($id);
            return response()->json($city, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Ville introuvable'], 404);
        }
    }

    // PUT/PATCH /cities/{id} → modifier une ville
    public function updateCity(Request $r, $id)
    {
        try {
            $city = City::findOrFail($id);
            $data = $r->validate([
                'country_id' => 'sometimes|exists:countries,id',
                'name' => 'sometimes|string|max:150',
            ]);
            $city->update($data);
            return response()->json($city, 200);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Erreur de validation', 'details' => $e->errors()], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Ville introuvable'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Erreur interne', 'details' => $e->getMessage()], 500);
        }
    }

    // DELETE /cities/{id} → supprimer une ville
    public function destroyCity($id)
    {
        try {
            $city = City::findOrFail($id);
            $city->delete();
            return response()->json(['message' => 'Ville supprimée avec succès'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Ville introuvable'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Erreur interne', 'details' => $e->getMessage()], 500);
        }
    }
}
