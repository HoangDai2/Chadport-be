<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewAddress extends Model
{
    protected $table = 'newaddress';
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'phone_number_address',
        'address',
        'specific_address',
        'is_default',
    ];

    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
