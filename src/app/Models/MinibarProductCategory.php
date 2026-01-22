<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MinibarProductCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'icon',
        'active'
    ];

    protected $casts = [
        'active' => 'boolean'
    ];

    public function products()
    {
        return $this->hasMany(MinibarProduct::class, 'category_id');
    }

    public function sellableProducts()
    {
        return $this->hasMany(MinibarProduct::class, 'category_id')
                    ->where('is_sellable', true);
    }

    public function nonSellableProducts()
    {
        return $this->hasMany(MinibarProduct::class, 'category_id')
                    ->where('is_sellable', false);
    }
}
