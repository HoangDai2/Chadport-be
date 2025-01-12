<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoucherDetails extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'voucher_id', 'used_at'];

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
