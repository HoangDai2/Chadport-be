<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;

    protected $table = 'comment';
    
    protected $fillable = [
        'comment_id',
        'product_item_id',
        'user_id',
        'content',
        'rating',
        'image',
    ];

    //  Relationship
    public function User() {
        return $this->belongsTo(User::class,'user_id', 'id');
    }

    public function product() {
        return $this->belongsTo(Product::class);
    }

}
