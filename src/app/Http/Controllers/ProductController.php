<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Product;
use App\ProductImage;
use App\AttributeProduct;
use App\Http\Controllers\CategoryControllers;
use Illuminate\Support\Facades\DB;
class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(int $categoryId)
    {
        return Product::where('category_id', $categoryId)->with("images")->with("attributes")->with("presentations.prices")->get();

    }
    
    
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function activeProducts(int $categoryId)
    {
        return Product::where('category_id', $categoryId)->with("images")->with("attributes")->with("presentations.prices")->where("status",1)->get();
    }

    public function activeAllProducts()
    {
        return Product::with('category')->with("images")->with("attributes")->with("presentations.prices")->where("status",1)->get();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function activeProviderProducts(int $providerId)
    {
        $productsIds = DB::table('products')
            ->join('product_presentations', 'product_presentations.product_id', '=', 'products.id')
            ->join('product_presentation_provider_prices', 'product_presentation_provider_prices.product_presentation_id', '=', 'product_presentations.id')
            ->select('products.id')
            ->where("product_presentation_provider_prices.provider_id",$providerId)
            ->get();
        $ids = [];
        foreach($productsIds->toArray() as $id){
            $ids[]=$id->id;
        }
        return Product::whereIn('id', $ids)->with("images")->with("attributes")->with("presentations.prices")->where("status",1)->get();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $product = Product::create($request->all());
        CategoryController::newVersion();
        return response()->json(ProductController::getOneProductInfo($product->id), 201);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function storeImages(Request $request, Product $product)
    {
        $path = $request->file('image_product')->store("products",'public');
        $productImage = new ProductImage();
        $productImage->url_image = env('PUBLIC_ASSETS_URL')."/{$path}";
        $productImage->status = true;
        $productImage->product_id = $product->id;
        $productImage->save();
        CategoryController::newVersion();
        return response()->json(ProductController::getOneProductInfo($product->id), 201);
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function addAttrib(Request $request, Product $product)
    {
        $prdattr = new AttributeProduct();
        $prdattr->attribute_id=$request->input('attribute_id');
        $prdattr->product_id=$product->id;
        $prdattr->value=$request->input('value');
        $prdattr->save();
        CategoryController::newVersion();
        return response()->json(ProductController::getOneProductInfo($product->id), 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Product $product)
    {
        $product->update($request->all());
        CategoryController::newVersion();
        return response()->json(ProductController::getOneProductInfo($product->id), 201);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroyImage(int $image)
    {
        $res = ProductImage::find($image);
        $res->delete();
        CategoryController::newVersion();
        return response()->json(ProductController::getOneProductInfo($res->product_id), 200 );
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    /**
     * Search product list.
     *
     * @param  int  $term
     * @return \Illuminate\Http\Response
     */
    public function search($term)
    {
        return Product::with("images")
            ->with("attributes")
            ->with("presentations.prices")
            ->with("presentations.prices.provider")
            ->with("category.parent")
            ->whereRaw("status = 1 and ( name like '%{$term}%' or sku like '%{$term}%' or description like '%{$term}%' ) ")
            ->get();
    }

    public static function getOneProductInfo(int $prodId){
        return Product::with("images")->with("attributes")->with("presentations.prices")->find($prodId);
    }
}
