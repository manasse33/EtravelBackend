<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ConfigController;
use App\Http\Controllers\DestinationPackageController;
use App\Http\Controllers\OuikenacController;
use App\Http\Controllers\CityTourController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\AdminAuthController;

Route::get('/countries', [ConfigController::class,'countries']);
Route::post('/countries', [ConfigController::class,'storeCountry']);
Route::post('/cities', [ConfigController::class,'storeCity']);

// DESTINATIONS
Route::get('/destinations', [DestinationPackageController::class,'index']);
Route::post('/destinations', [DestinationPackageController::class,'store']);
Route::get('/destinations/{id}', [DestinationPackageController::class,'show']);
Route::put('/destinations/{id}', [DestinationPackageController::class,'update']);
Route::delete('/destinations/{id}', [DestinationPackageController::class,'destroy']);

// OUIKENAC
Route::get('/ouikenac', [OuikenacController::class,'index']);
Route::post('/ouikenac', [OuikenacController::class,'store']);
Route::get('/ouikenac/{id}', [OuikenacController::class,'show']);
Route::put('/ouikenac/{id}', [OuikenacController::class,'update']);
Route::delete('/ouikenac/{id}', [OuikenacController::class,'destroy']);

// CITY TOUR
Route::get('/city-tours', [CityTourController::class,'index']);
Route::post('/city-tours', [CityTourController::class,'store']);
Route::get('/city-tours/{id}', [CityTourController::class,'show']);
Route::put('/city-tours/{id}', [CityTourController::class,'update']);
Route::delete('/city-tours/{id}', [CityTourController::class,'destroy']);

// RESERVATIONS
Route::post('/reservations', [ReservationController::class,'store']);
Route::get('/reservations', [ReservationController::class,'index']);
Route::put('/reservations/{id}/status', [ReservationController::class,'updateStatus']);


Route::post('/register', [AdminAuthController::class, 'register']);
Route::post('/login', [AdminAuthController::class, 'login']);

// Route::middleware('auth:sanctum')->group(function () {
//     Route::post('/admin/logout', [AdminAuthController::class, 'logout']);

  
//     Route::post('/admin/packages', [PackageController::class, 'store']);
//     Route::put('/admin/packages/{id}', [PackageController::class, 'update']);
//     Route::delete('/admin/packages/{id}', [PackageController::class, 'destroy']);

//     Route::get('/admin/reservations', [ReservationController::class, 'index']);
//     Route::put('/admin/reservations/{id}/validate', [ReservationController::class, 'validateReservation']);
//     Route::put('/admin/reservations/{id}/cancel', [ReservationController::class, 'cancelReservation']);
// });




Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
