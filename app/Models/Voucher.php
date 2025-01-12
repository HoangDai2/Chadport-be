<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory;

    protected $table = 'vouchers';
    protected $fillable = [
        'id',
        'code',
        'discount_type',
        'discount_value',
        'expires_at',
        'usage_limit',
        'used_count',
    ];

    public function Order()
    {
        return $this->hasOne(Order::class);
    }
    public function details()
    {
        return $this->hasMany(VoucherDetails::class);
    }
}
