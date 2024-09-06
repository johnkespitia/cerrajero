<?php

namespace App\Http\Controllers;

use App\Models\KioskCategory;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class KioskCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return KioskCategory::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:kiosk_categories',
            'active' => 'boolean',
        ]);

        $kioskCategory = KioskCategory::create($request->all());
        return response()->json($kioskCategory, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(KioskCategory $kioskCategory)
    {
        return $kioskCategory;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, KioskCategory $kioskCategory)
    {
        $request->validate([
            'name' => 'required|unique:kiosk_categories,name,' . $kioskCategory->id,
            'active' => 'boolean',
        ]);

        $kioskCategory->update($request->all());
        return response()->json($kioskCategory, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(KioskCategory $kioskCategory)
    {
        $kioskCategory->delete();
        return response()->json(null, 204);
    }
}