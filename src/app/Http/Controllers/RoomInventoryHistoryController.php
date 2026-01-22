<?php

namespace App\Http\Controllers;

use App\Models\RoomInventoryHistory;
use App\Models\Room;
use App\Models\CommonArea;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class RoomInventoryHistoryController extends Controller
{
    public function index(Request $request)
    {
        $query = RoomInventoryHistory::with(['item', 'assignable', 'user', 'assignment']);

        // Filtros
        if ($request->has('room_id')) {
            $query->where(function($q) use ($request) {
                $q->where(function($subQ) use ($request) {
                    $subQ->where('assignable_type', Room::class)
                         ->where('assignable_id', $request->room_id);
                })->orWhere(function($subQ) use ($request) {
                    $subQ->where('old_assignable_type', Room::class)
                         ->where('old_assignable_id', $request->room_id);
                })->orWhere(function($subQ) use ($request) {
                    $subQ->where('new_assignable_type', Room::class)
                         ->where('new_assignable_id', $request->room_id);
                });
            });
        }

        if ($request->has('common_area_id')) {
            $query->where(function($q) use ($request) {
                $q->where(function($subQ) use ($request) {
                    $subQ->where('assignable_type', CommonArea::class)
                         ->where('assignable_id', $request->common_area_id);
                })->orWhere(function($subQ) use ($request) {
                    $subQ->where('old_assignable_type', CommonArea::class)
                         ->where('old_assignable_id', $request->common_area_id);
                })->orWhere(function($subQ) use ($request) {
                    $subQ->where('new_assignable_type', CommonArea::class)
                         ->where('new_assignable_id', $request->common_area_id);
                });
            });
        }

        if ($request->has('item_id')) {
            $query->where('item_id', $request->item_id);
        }

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $history = $query->orderBy('created_at', 'desc')
                        ->paginate($request->get('per_page', 50));

        return response($history, Response::HTTP_OK);
    }

    public function show(RoomInventoryHistory $roomInventoryHistory)
    {
        $roomInventoryHistory->load(['item.category', 'assignable', 'user', 'assignment']);
        return response($roomInventoryHistory, Response::HTTP_OK);
    }

    public function getByRoom($roomId)
    {
        $history = RoomInventoryHistory::where(function($q) use ($roomId) {
                $q->where(function($subQ) use ($roomId) {
                    $subQ->where('assignable_type', Room::class)
                         ->where('assignable_id', $roomId);
                })->orWhere(function($subQ) use ($roomId) {
                    $subQ->where('old_assignable_type', Room::class)
                         ->where('old_assignable_id', $roomId);
                })->orWhere(function($subQ) use ($roomId) {
                    $subQ->where('new_assignable_type', Room::class)
                         ->where('new_assignable_id', $roomId);
                });
            })
            ->with(['item.category', 'user', 'assignment'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response($history, Response::HTTP_OK);
    }

    public function getByCommonArea($commonAreaId)
    {
        $history = RoomInventoryHistory::where(function($q) use ($commonAreaId) {
                $q->where(function($subQ) use ($commonAreaId) {
                    $subQ->where('assignable_type', CommonArea::class)
                         ->where('assignable_id', $commonAreaId);
                })->orWhere(function($subQ) use ($commonAreaId) {
                    $subQ->where('old_assignable_type', CommonArea::class)
                         ->where('old_assignable_id', $commonAreaId);
                })->orWhere(function($subQ) use ($commonAreaId) {
                    $subQ->where('new_assignable_type', CommonArea::class)
                         ->where('new_assignable_id', $commonAreaId);
                });
            })
            ->with(['item.category', 'user', 'assignment'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response($history, Response::HTTP_OK);
    }

    public function getByItem($itemId)
    {
        $history = RoomInventoryHistory::where('item_id', $itemId)
            ->with(['assignable', 'user', 'assignment'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response($history, Response::HTTP_OK);
    }

    public function getByAssignment($assignmentId)
    {
        $history = RoomInventoryHistory::where('assignment_id', $assignmentId)
            ->with(['item.category', 'user', 'assignable'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response($history, Response::HTTP_OK);
    }
}
