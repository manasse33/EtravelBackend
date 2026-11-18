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
        $actor = $request->user();
        if (!$actor || $actor->role !== 'super_admin') {
            return response()->json(['message' => 'Action non autorisee'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins',
            'password' => 'required|string|min:6|confirmed',
        ]);

        Admin::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json(['message' => 'Admin cree avec succes']);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $admin = Admin::where('email', $request->email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email ou mot de passe incorrect',
            ], 401);
        }

        $token = $admin->createToken('admin-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'admin' => $admin,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Deconnecte avec succes']);
    }

    public function validateReservation(Request $request, $id)
    {
        $res = Reservation::findOrFail($id);
        $res->status = 'approved';
        $res->validated_by = $request->user()?->id;
        $res->save();

        return response()->json(['message' => 'Reservation validee']);
    }

    public function cancelReservation(Request $request, $id)
    {
        $res = Reservation::findOrFail($id);
        $res->status = 'cancelled';
        $res->validated_by = $request->user()?->id;
        $res->save();

        return response()->json(['message' => 'Reservation annulee']);
    }
}
