<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Country;
use App\Models\City;

class ConfigController extends Controller {
    public function countries(){ return response()->json(Country::with('cities')->get()); }
    public function storeCountry(Request $r){
        $c = Country::create($r->validate(['code'=>'required','name'=>'required']));
        return response()->json($c,201);
    }
    public function storeCity(Request $r){
        $v = $r->validate(['country_id'=>'required|exists:countries,id','name'=>'required']);
        $city = City::create($v);
        return response()->json($city,201);
    }
}
