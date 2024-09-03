<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Provider;
use App\ProviderSchedule;
use Illuminate\Support\Facades\Storage;

class ProviderController extends Controller
{

    const WEEK_DAYS = [ "lunes", "martes", "miercoles", "jueves", "viernes", "sabado", "domingo" ];
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return Provider::with("provider_type")->with("schedule")->with("productsPrice.presentation.product")->get();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $provider =  Provider::create($request->all());
        if($request->file('image_provider')){
            $path = $request->file('image_provider')->store("providers",'public');
            $provider->image_provider = env('PUBLIC_ASSETS_URL')."/{$path}";
            $provider->save();
        }
        CategoryController::newVersion();
        return response()->json(Provider::with("provider_type")->with("schedule")->find($provider->id), 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Provider $provider )
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request,Provider $provider)
    {
        $provider->update($request->all());
        if($request->file('image_provider')){
            $path = $request->file('image_provider')->store("providers",'public');
            $provider->image_provider = env('PUBLIC_ASSETS_URL')."/{$path}";
            $provider->save();
        }

        CategoryController::newVersion();
        return response()->json(Provider::with("provider_type")->with("schedule")->find($provider->id), 201);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Provider $id)
    {
        //
    }
}
