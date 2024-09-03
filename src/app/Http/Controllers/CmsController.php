<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Banner;
use App\Http\Controllers\CategoryControllers;
use App\FeaturedProduct;
class CmsController extends Controller
{
    
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function storeBanner(Request $request)
    {
        $path = $request->file('url_image')->store("banner",'public');
        $path_mov = $request->file('url_image_mov')->store("banner",'public');
        $bannerImage = new Banner();
        $bannerImage->url_image = env('PUBLIC_ASSETS_URL')."/{$path}";
        $bannerImage->url_image_mov = env('PUBLIC_ASSETS_URL')."/{$path_mov}";
        $bannerImage->url_link = $request->input('url_link');
        $bannerImage->status = true;
        $bannerImage->order = $request->input('order');
        $bannerImage->title = $request->input('title');
        $bannerImage->description = $request->input('description');
        $bannerImage->save();
        CategoryController::newVersion();
        return response()->json($bannerImage, 201);
    }

    public function destroyBanner(Banner $banner)
    {
        $banner->delete();
        CategoryController::newVersion();
        return response()->json("", 204);
    }
    
    public function addFeaturedProduct(Request $request){   
        $fproduct = new FeaturedProduct();
        $fproduct->order = $request->input('order');
        $fproduct->category_id = $request->input('category_id');
        $fproduct->product_id = $request->input('product_id');
        $fproduct->status = true;
        $fproduct->save();
        CategoryController::newVersion();
        $fproduct = FeaturedProduct::where("id",$fproduct->id)->with("product.images")
        ->with("product.category")
        ->with("product.presentations.prices")->first();
        return response()->json($fproduct, 201);
    }

    public function destroyFeaturedProduct(FeaturedProduct $featuredProduct)
    {
        $featuredProduct->delete();
        CategoryController::newVersion();
        return response()->json("", 204);
    }

    public function index(){
        return response()->json(Banner::all(), 200);
    }
}
