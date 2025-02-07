<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    use HasFactory;
    protected $table = 'brands';
    protected $fillable = [
        'id',
        'name',
        'description',
    ];


    //  Relationship

    public function Product() {
        return $this->hasOne(Product::class);
    }
}
