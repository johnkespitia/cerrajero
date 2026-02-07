<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryConsumptionLog extends Model
{
    protected $table = 'inventory_consumption_log';

    protected $fillable = [
        'order_item_id',
        'employee_meal_item_id',
        'inventory_batch_id',
        'input_id',
        'quantity_consumed',
        'measure_id',
    ];

    protected $casts = [
        'quantity_consumed' => 'decimal:4',
    ];

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function employeeMealItem()
    {
        return $this->belongsTo(EmployeeMealItem::class, 'employee_meal_item_id');
    }

    public function inventoryBatch()
    {
        return $this->belongsTo(InventoryBatch::class, 'inventory_batch_id');
    }

    public function input()
    {
        return $this->belongsTo(InventoryInput::class, 'input_id');
    }

    public function measure()
    {
        return $this->belongsTo(InventoryMeasure::class, 'measure_id');
    }
}
