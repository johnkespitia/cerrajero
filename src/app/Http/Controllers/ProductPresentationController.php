<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Product;
use App\ProductPresentation;
use App\ProductPresentationProviderPrice;
class ProductPresentationController extends Controller
{
    public function store(Request $request, Product $product){
        $presentation = new ProductPresentation;
        $presentation->name_presentation=$request->input("name_presentation");
        $presentation->internal_sku=$request->input("internal_sku");
        $presentation->special_description=$request->input("special_description");
        $presentation->product_id= $product->id;
        $presentation->status=true;
        $presentation->save();

        $price = new ProductPresentationProviderPrice();
        $price->price_provider=$request->input("price_provider");
        $price->price=$request->input("price");
        $price->status=true;
        $price->product_presentation_id=$presentation->id;
        $price->qty = $request->input("qty");
        $price->provider_id=$request->input("provider_id");
        $price->external_url=$request->input("external_url");
        $price->box_width=$request->input("box_width");
        $price->box_height=$request->input("box_height");
        $price->box_length=$request->input("box_length");
        $price->box_weight=$request->input("box_weight");
        $price->save();
        CategoryController::newVersion();
        return response()->json(ProductController::getOneProductInfo($product->id), 201);
    }
    
    public function presentationAvailable( ProductPresentation $presentation){
        $presentation->status=!$presentation->status;
        $presentation->update();
        CategoryController::newVersion();
        return response()->json(ProductController::getOneProductInfo($presentation->product_id), 201);
    }

    public function update(Request $request, ProductPresentation $presentation)
    {
        $presentation->update($request->all());
        CategoryController::newVersion();
        return response()->json(ProductController::getOneProductInfo($presentation->product_id), 201);
    }

    public function updatePriceProvider(Request $request, ProductPresentationProviderPrice $productPrice,ProductPresentation $productPresentation)
    {
        $productPrice->update($request->all());
        CategoryController::newVersion();
        return response()->json(ProductController::getOneProductInfo($productPresentation->product_id), 201);
    }
    public function storePriceProvider(Request $request, ProductPresentation $productPresentation)
    {
        $price = new ProductPresentationProviderPrice();
        $price->price_provider=$request->input("price_provider");
        $price->price=$request->input("price");
        $price->status=true;
        $price->product_presentation_id=$productPresentation->id;
        $price->qty = $request->input("qty");
        $price->provider_id=$request->input("provider_id");
        $price->external_url=$request->input("external_url");
        $price->save();
        CategoryController::newVersion();
        return response()->json(ProductController::getOneProductInfo($productPresentation->product_id), 201);
    }
}
