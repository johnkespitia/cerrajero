<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'contact_name', 'email', 'phone', 'address',
        'tax_id', 'service_type', 'rating', 'notes', 'active'
    ];
    
    protected $casts = [
        'rating' => 'decimal:2',
        'active' => 'boolean'
    ];
    
    public function maintenanceWorks()
    {
        return $this->hasMany(MaintenanceWork::class, 'supplier_id');
    }
}
