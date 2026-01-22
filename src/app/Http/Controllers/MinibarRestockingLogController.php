<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\MinibarProduct;
use App\Models\MinibarRestockingLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MinibarRestockingLogController extends Controller
{
    /**
     * Listar registros de reposición
     */
    public function index(Request $request)
    {
        $query = MinibarRestockingLog::with(['room', 'product', 'restockedBy']);

        if ($request->has('room_id')) {
            $query->where('room_id', $request->room_id);
        }

        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('date_from')) {
            $query->where('restocked_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('restocked_at', '<=', $request->date_to);
        }

        $logs = $query->orderBy('restocked_at', 'desc')->paginate($request->get('per_page', 50));

        return response($logs, Response::HTTP_OK);
    }

    /**
     * Obtener reposiciones por habitación
     */
    public function getByRoom(Room $room)
    {
        $logs = MinibarRestockingLog::where('room_id', $room->id)
            ->with(['product', 'restockedBy'])
            ->orderBy('restocked_at', 'desc')
            ->get();

        return response($logs, Response::HTTP_OK);
    }

    /**
     * Obtener reposiciones por producto
     */
    public function getByProduct(MinibarProduct $product)
    {
        $logs = MinibarRestockingLog::where('product_id', $product->id)
            ->with(['room', 'restockedBy'])
            ->orderBy('restocked_at', 'desc')
            ->get();

        return response($logs, Response::HTTP_OK);
    }
}
