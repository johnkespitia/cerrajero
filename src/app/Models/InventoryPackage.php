<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryPackage extends Model
{
    use HasFactory;
    protected $fillable = [
        'package_name',
        'stock',
        'units_by',
        'status'
    ];

    public function supplies()
    {
        return $this->hasMany(InventoryPackageSupply::class, 'package_id', 'id');
    }

    public function consumes()
    {
        return $this->hasMany(InventoryPackageConsume::class, 'package_id', 'id');
    }
}
