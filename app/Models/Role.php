<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $table = 'role';
    protected $fillable = [
        'id',
        'name',
    ];
    public function User() {
        return $this->belongsToMany(User::class, 'role_id');
    }
    
}
