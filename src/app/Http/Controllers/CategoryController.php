<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Category;
use App\Version;
class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return Category::whereNull('category_parent_id')->with("children")
        ->with("featured_products.product.images")
        ->with("featured_products.product.category")
        ->with("featured_products.product.presentations.prices")->get();
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function activeCategories()
    {
        return Category::whereNull('category_parent_id')
        ->with("children")
        ->with("featured_products.product.images")
        ->with("featured_products.product.category")
        ->with("featured_products.product.presentations.prices")
        ->where("status", 1)->orderBy('id', 'asc')->get();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $Category =  Category::create($request->all());
        if($request->file('image_category')){
            $path = $request->file('image_category')->store("categories",'public');
            $Category->image = env('PUBLIC_ASSETS_URL')."/{$path}";
            $Category->save();
        }
        if($request->file('icon')){
            $path = $request->file('icon')->store("categories",'public');
            $Category->icon = env('PUBLIC_ASSETS_URL')."/{$path}";
            $Category->save();
        }
        CategoryController::newVersion();
        return response()->json($Category, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Category $Category )
    {
        //
    }

    /**
     * return version database categories.
     *
     * @return \Illuminate\Http\Response
     */
    public function getVersion()
    {
        return response()->json(Version::orderBy('id', 'desc')->first(), 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request,Category $Category)
    {
        $Category->update($request->all());
        if($request->file('image_category')){
            $path = $request->file('image_category')->store("Categories",'public');
            $Category->image = env('PUBLIC_ASSETS_URL')."/{$path}";
            $Category->save();
        }
        if($request->file('icon')){
            $path = $request->file('icon')->store("categories",'public');
            $Category->icon = env('PUBLIC_ASSETS_URL')."/{$path}";
            $Category->save();
        }
        CategoryController::newVersion();
        return response()->json($Category, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Category $id)
    {
        //
    }

    public static function newVersion(){
        return Version::create(
            [
                'version_number' => time(),
            ]
        );
    }
}
