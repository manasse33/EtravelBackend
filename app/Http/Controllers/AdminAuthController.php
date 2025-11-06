<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $admin = Admin::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json(['message' => 'Admin créé avec succès']);
    }

      public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $admin = Admin::where('email', $request->email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email ou mot de passe incorrect'
            ], 401);
        }

        // Générer un token sanctum
        $token = $admin->createToken('admin-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'admin' => $admin,
            'token' => $token
        ]);
    }


    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Déconnecté avec succès']);
    }

    public function validateReservation($id)
{
    $res = Reservation::findOrFail($id);
    $res->status = 'validated';
    $res->save();

    return response()->json(['message' => 'Réservation validée']);
}

public function cancelReservation($id)
{
    $res = Reservation::findOrFail($id);
    $res->status = 'cancelled';
    $res->save();

    return response()->json(['message' => 'Réservation annulée']);
}

}
