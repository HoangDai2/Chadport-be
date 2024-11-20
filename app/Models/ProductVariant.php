<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use HasFactory;

    protected $table = 'product_variants';

    protected $fillable = ['product_id', 'col_id', 'size_id', 'quantity'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function color()
    {
        return $this->belongsTo(Color::class, 'col_id');
    }

    public function size()
    {
        return $this->belongsTo(Size::class, 'size_id');
    }
//     public function cartItems() {
//         return $this->hasMany(CartItem::class);
//     }
}
