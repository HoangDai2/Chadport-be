<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart_item extends Model
{
    use HasFactory;

    protected $table = 'cart_item';
    protected $fillable = [
        'id',
        'user_id', 
        'cart_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'price',
    ];

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }
    public function items()
    {
        return $this->hasMany(Cart_item::class, 'cart_id');
    }
    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
