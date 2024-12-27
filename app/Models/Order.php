<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $table = 'order';
    protected $fillable = [
        'id',
        'voucher_id',
        'user_id',
        'oder_number',
        'payment_method',
        'total_money',
        'shipping_address',
        'billing_address',
        'status',
        'note_user',
        'note_admin',
        'check_refund'
    ];

    //  Relationship
    public function User() {
        return $this->belongsTo(User::class);
    }

    public function Voucher() {
        return $this->belongsTo(Voucher::class);
    }

    public function orderDetails() {
        return $this->hasMany(OrderDetail::class);
    }
}
