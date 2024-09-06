<?php

namespace App\Http\Controllers;

use App\Models\ProducedBatch;
use Illuminate\Http\Request;

class ProducedBatchController extends Controller
{
    public function index()
    {
        $batches = ProducedBatch::all();
        return response()->json($batches);
    }
    public function show(ProducedBatch $producedBatch)
    {
        return response()->json($producedBatch);
    }


    public function update(Request $request, ProducedBatch $producedBatch)
    {
        $validatedData = $request->validate([
            'order_item_id' => 'sometimes|exists:products,id',
            'quantity' => 'sometimes|numeric',
            'expiration_date' => 'sometimes|date',
            'batch_serial' => 'sometimes',
        ]);
        $producedBatch->update($validatedData);
        return response()->json($producedBatch);
    }
}
