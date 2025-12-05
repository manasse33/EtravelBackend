<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ConfigController;
use App\Http\Controllers\DestinationPackageController;
use App\Http\Controllers\OuikenacController;
use App\Http\Controllers\CityTourController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\AdminAuthController;

/*
|--------------------------------------------------------------------------
| ROUTES PUBLIQUES
|--------------------------------------------------------------------------
*/

// Auth
Route::post('/register', [AdminAuthController::class, 'register']);
Route::post('/login', [AdminAuthController::class, 'login'])->name('login');


// Reservations (EXCEPTÉES comme demandé)
Route::post('/reservations', [ReservationController::class,'store']);
Route::get('/reservations', [ReservationController::class,'index']);
Route::put('/reservations/{id}/status', [ReservationController::class,'updateStatus']);

// Test upload image
Route::post('/testImage', [CityTourController::class, 'storeImageTest']);

// GET publics
Route::get('/countries', [ConfigController::class, 'indexCountries']);
Route::get('/countries/{id}', [ConfigController::class, 'showCountry']);

Route::get('/cities', [ConfigController::class, 'indexCities']);
Route::get('/cities/{id}', [ConfigController::class, 'showCity']);

Route::get('/destinations', [DestinationPackageController::class,'index']);
Route::get('/destinations/{id}', [DestinationPackageController::class,'show']);

Route::get('/ouikenac', [OuikenacController::class,'index']);
Route::get('/ouikenac/{id}', [OuikenacController::class,'show']);

Route::get('/city-tours', [CityTourController::class,'index']);
Route::get('/city-tours/{id}', [CityTourController::class,'show']);


/*
|--------------------------------------------------------------------------
| ROUTES PROTÉGÉES (auth:sanctum)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // Countries
    Route::post('/countries', [ConfigController::class, 'storeCountry']);
    Route::put('/countries/{id}', [ConfigController::class, 'updateCountry']);
    Route::patch('/countries/{id}', [ConfigController::class, 'updateCountry']);
    Route::delete('/countries/{id}', [ConfigController::class, 'destroyCountry']);

    // Cities
    Route::post('/cities', [ConfigController::class, 'storeCity']);
    Route::put('/cities/{id}', [ConfigController::class, 'updateCity']);
    Route::patch('/cities/{id}', [ConfigController::class, 'updateCity']);
    Route::delete('/cities/{id}', [ConfigController::class, 'destroyCity']);

    // Destinations
    Route::post('/destinations', [DestinationPackageController::class,'store']);
    Route::put('/destinations/{id}', [DestinationPackageController::class,'update']);
    Route::delete('/destinations/{id}', [DestinationPackageController::class,'destroy']);

    // Ouikenac
    Route::post('/ouikenac', [OuikenacController::class,'store']);
    Route::put('/ouikenac/{id}', [OuikenacController::class,'update']);
    Route::delete('/ouikenac/{id}', [OuikenacController::class,'destroy']);

    // City Tours
    Route::post('/city-tours', [CityTourController::class,'store']);
    Route::put('/city-tours/{id}', [CityTourController::class,'update']);
    Route::delete('/city-tours/{id}', [CityTourController::class,'destroy']);

    // User déconnecté
    Route::post('/logout', [AdminAuthController::class, 'logout']);

    Route::put('/reservations/{id}/status', [ReservationController::class,'updateStatus']);
});


// Vérification user connecté
