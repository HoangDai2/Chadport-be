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
        'cart_id',
        'product_item_id',
        'checked',
        'quantity',
        'price',
        'is_buy_now'
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

    public function productItem()
    {
        return $this->belongsTo(ProductItems::class, 'product_item_id', 'id');
    }
}
