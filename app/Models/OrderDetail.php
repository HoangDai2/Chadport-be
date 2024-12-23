<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderDetail extends Model
{
    use HasFactory;

    protected $table = 'order_detail';
    protected $fillable = [
        'id',
        'order_id',
        'product_id',
        'quantity',
        'price',
        'total_price',
        'product_item_id'
    ];

    public function Order() {
        return $this->belongsTo(Order::class);
    }
    
    public function ProductItem() {
        return $this->belongsTo(ProductItems::class);
    }

    public function size()
    {
        return $this->belongsTo(Size::class, 'size_id');
    }

    public function color()
    {
        return $this->belongsTo(Color::class, 'color_id');
    }
}
