<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\DestinationPackage;
use App\Models\PackagePrice;

class DestinationPackageController extends Controller {
    public function index(){ return DestinationPackage::with(['departureCountry','arrivalCountry','prices','services'])->get(); }

    public function store(Request $r){
        $data = $r->validate([
            'title'=>'required|string',
            'description'=>'nullable|string',
            'image'=>'nullable|string',
            'departure_country_id'=>'required|exists:countries,id',
            'arrival_country_id'=>'required|exists:countries,id',
            'services'=>'array|nullable',
            'prices'=>'array|nullable'
        ]);
        $pkg = DestinationPackage::create($data);
        if(!empty($data['services'])){
            foreach($data['services'] as $s){
                $pkg->services()->attach($s['service_id'], ['details'=> $s['details'] ?? null]);
            }
        }
        if(!empty($data['prices'])){
            foreach($data['prices'] as $p){
                $pkg->prices()->create($p);
            }
        }
        return response()->json($pkg->load('prices','services'),201);
    }

    public function show($id){ return DestinationPackage::with('prices','services')->findOrFail($id); }

    public function update(Request $r,$id){
        $pkg = DestinationPackage::findOrFail($id);
        $pkg->update($r->only(['title','description','image','min_people','max_people','departure_city_id','arrival_city_id']));
        return response()->json($pkg);
    }

    public function destroy($id){ DestinationPackage::findOrFail($id)->delete(); return response()->json(['message'=>'deleted']); }
}
