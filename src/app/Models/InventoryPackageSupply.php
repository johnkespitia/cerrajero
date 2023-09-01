<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryPackageSupply extends Model
{
    use HasFactory;

    protected $fillable = [
        'supply_date',
        'stock',
        'package_id',
        'user_id'
    ];

    public function package()
    {
        return $this->belongsTo(InventoryPackage::class, 'package_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
