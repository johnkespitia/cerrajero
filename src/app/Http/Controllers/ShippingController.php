<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\City;
use App\Provider;
use App\PriceCity;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
class ShippingController extends Controller
{
    public function quotation(Request $request){
        $address = $request->input("address");
        $city = City::find($address["city_id"]);
        $cart = $request->input("cart");
        if(env("TYPE_SHIPPING")=="PER_PRODUCT"){
            return response()->json($this->shippingPerProduct($cart, $address, $city), 200);
        }else{
            return response()->json($this->shippingPerProvider($cart, $address, $city), 200);
        }
    }

    protected function shippingPerProduct($cart, $address, $city){
        $shipping=[];
        foreach ($cart as $idx => $item) {
            $selected_presentation = current(array_filter($item["product"]["presentations"],function($it) use($item){
                return ($it['id']==$item["product_presentation_selected"]);
            }));
            $selecte_price = current(array_filter($selected_presentation["prices"],function($it) use($item){
                return ($it['id']==$item["product_price_selected"]);
            }));
            $prv = Provider::where("id",$selecte_price["provider_id"])->with("city")->first();
            $quotation= [
                "product_id"=>$item["product"]["id"],
                "product_presentation_selected"=>$item["product_presentation_selected"],
                "product_price_selected"=>$item["product_price_selected"],
                "quantity"=>$item["quantity"],
            ];
            if(env("TOKEN_ENVIOCLICK")){
                try{
                    $response = Http::withHeaders([
                        'AuthorizationKey' => env("TOKEN_ENVIOCLICK"),
                    ])->post(env("URL_ENVIOCLICK").'quotation', [
                        "packages"=> [
                            [
                                "weight"=> $selecte_price["box_weight"],
                                "length"=> $selecte_price["box_length"],
                                "height"=> $selecte_price["box_height"],
                                "width"=> $selecte_price["box_width"]
                              ]
                          ],
                          "description"=> $item["product"]["name"]." ".$selected_presentation["name_presentation"],
                          "contentValue"=> $selecte_price["price"],
                          "origin"=>[
                            "daneCode"=> $prv->city->code,
                            "address"=> $prv->address
                          ],
                          "destination"=>[
                            "daneCode"=> $city->code,
                            "address"=> "{$address["address"]} {$address["address_remarks"]} {$address["arrival_directions"]}"
                        ]
                    ]);
                    if($response->successful()){
                        $data = json_decode($response->getBody());
                        if($data->status=="OK"){
                            $quotation['rate'] = $data->data->rates[0];
                        }else{
                            print_r($data); die;
                            $quotation['rate'] = [
                                "idRate"=>"1",
                                "total"=>env("DEFAULT_SHIPPING_COST"),
                                "idProduct"=> 1,
                                "product"=> "Dos días",
                                "vehicle"=> "Manual",
                                "idCarrier"=> "Default",
                                "carrier"=> "Default",
                                "publicPrice"=> env("DEFAULT_SHIPPING_COST"),
                                "amountInsurance"=> 0,
                                "deliveryDays"=> 1
                            ];
                        }
                    }else{
                        $quotation['rate'] = [
                            "idRate"=>"1",
                            "total"=>env("DEFAULT_SHIPPING_COST"),
                            "idProduct"=> 1,
                            "product"=> "Tres días",
                            "vehicle"=> "Manual",
                            "idCarrier"=> "Default",
                            "carrier"=> "Default",
                            "publicPrice"=> env("DEFAULT_SHIPPING_COST"),
                            "amountInsurance"=> 0,
                            "deliveryDays"=> 1
                        ];
                    }
                }catch(\Exception $e){
                    Log::info(print_r($e->getMessage(),1));
                }
            }else{
                
                $quotation['rate'] = [
                    "idRate"=>"1",
                    "total"=>env("DEFAULT_SHIPPING_COST"),
                    "idProduct"=> 1,
                    "product"=> "Dos días",
                    "vehicle"=> "Mercancía",
                    "idCarrier"=> "internal",
                    "carrier"=> "interA",
                    "publicPrice"=> env("DEFAULT_SHIPPING_COST"),
                    "amountInsurance"=> 0,
                    "deliveryDays"=> 1
                ];
            }
            $shipping[]=$quotation;
        }
        return $shipping;
    }

    protected function shippingPerProvider($cart, $address, $city){
        $shipping=[];
        $provider_filter=[];
        foreach ($cart as $idx => $item) {
            $selected_presentation = current(array_filter($item["product"]["presentations"],function($it) use($item){
                return ($it['id']==$item["product_presentation_selected"]);
            }));
            $selecte_price = current(array_filter($selected_presentation["prices"],function($it) use($item){
                return ($it['id']==$item["product_price_selected"]);
            }));
            if(!in_array($selecte_price["provider_id"],$provider_filter)){
                $prv = Provider::where("id",$selecte_price["provider_id"])->with("city")->first();
                try{
                    $mapUrl = "https://maps.googleapis.com/maps/api/directions/json?origin={$prv->latitude},{$prv->longitude}&destination={$address["latitude"]},{$address["longitude"]}&key=".env("GMAPS_APIKEY");
                    $response = Http::get($mapUrl);
                    $priceDelivery = env("DEFAULT_SHIPPING_COST");
                    if($response->successful()){
                        $data = json_decode($response->getBody());
                        $metersDistance = $data->routes[0]->legs[0]->distance->value; 
                        $kmDistance = $metersDistance/1000;
                        $aditionalKm = $kmDistance-env("SHIPPING_MIN_KM");
                        if($aditionalKm<0){
                            $priceDelivery = env("SHIPPING_MIN_COST");
                        }else{
                            $priceDelivery = env("SHIPPING_MIN_COST")+(ceil($aditionalKm)*env("SHIPPING_KM_ADD_COST"));
                        }
                    }else{
                        \Log::error("NO SUCCESS");
                        \Log::error(print_r($response,1));
                    }
                }catch(\Exception $e){
                    \Log::error($e->getMessage());
                    \Log::error(print_r($response,1));
                    $priceDelivery = env("DEFAULT_SHIPPING_COST");
                    $kmDistance = "DEFAULT KM";
                }
                    
                $quotation= [
                    "provider_id"=>$prv->id,
                    "quantity"=>1,
                ];
                
                $quotation['rate'] = [
                    "idRate"=>"1",
                    "total"=>$priceDelivery,
                    "idProduct"=> 1,
                    "product"=> "{$kmDistance} KM",
                    "vehicle"=> "parcero",
                    "idCarrier"=> "Internos",
                    "carrier"=> "Paceros",
                    "publicPrice"=> $priceDelivery,
                    "amountInsurance"=> 0,
                    "deliveryDays"=> 1
                ];
                $shipping[]=$quotation;
                $provider_filter[] = $selecte_price["provider_id"];
            }
        }
        return $shipping;
    }
}
