<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Reservation;

class ReservationController extends Controller {
    public function store(Request $r){
        $data = $r->validate([
            'reservable_type'=>'required|string', // e.g. App\Models\DestinationPackage
            'reservable_id'=>'required|integer',
            'full_name'=>'required|string',
            'email'=>'required|email',
            'phone'=>'nullable|string',
            'date_from'=>'nullable|date',
            'date_to'=>'nullable|date',
            'travelers'=>'required|integer|min:1',
            'total_price'=>'nullable|numeric',
            'currency'=>'nullable|string',
            'message'=>'nullable|string',
        ]);
        $res = Reservation::create($data);
        return response()->json($res,201);
    }

    public function index(Request $r){
        $q = Reservation::with('reservable');
        if($r->has('status')) $q->where('status',$r->status);
        return $q->orderBy('created_at','desc')->get();
    }

    public function updateStatus(Request $r, $id){
        $res = Reservation::findOrFail($id);
        $r->validate(['status'=>'required|in:pending,approved,rejected,cancelled']);
        $res->status = $r->status;
        $res->validated_by = $r->user()?->id ?? null; // if admin auth used
        $res->save();
        return response()->json($res);
    }
}
