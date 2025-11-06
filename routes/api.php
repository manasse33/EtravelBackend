<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\PackageController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\AdminAuthController;

Route::get('/packages', [PackageController::class, 'index']);
Route::get('/packages/{id}', [PackageController::class, 'show']);

Route::post('/reservations', [ReservationController::class, 'store']);
Route::get('/reservations', [ReservationController::class, 'index']);

Route::post('/register', [AdminAuthController::class, 'register']);
Route::post('/login', [AdminAuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/admin/logout', [AdminAuthController::class, 'logout']);

    // Gestion des packages
    Route::post('/admin/packages', [PackageController::class, 'store']);
    Route::put('/admin/packages/{id}', [PackageController::class, 'update']);
    Route::delete('/admin/packages/{id}', [PackageController::class, 'destroy']);

    // Gestion des réservations
    Route::get('/admin/reservations', [ReservationController::class, 'index']);
    Route::put('/admin/reservations/{id}/validate', [ReservationController::class, 'validateReservation']);
    Route::put('/admin/reservations/{id}/cancel', [ReservationController::class, 'cancelReservation']);
});




Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
