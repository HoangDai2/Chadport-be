<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Size extends Model
{
    use HasFactory;
    protected $table = 'sizes';
    protected $fillable = ['name', 'created_at', 'updated_at	'];

    public function variants()
    {
        return $this->hasMany(ProductItems::class, 'size_id');
    }

}
