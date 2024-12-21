<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;

    protected $table = 'comment';
    
    protected $fillable = [
        'product_item_id',
        'user_id',
        'content',
        'rating',
        'image',
        'like_count',
        'dislike_count',
        'reported',
    ];

    //  Relationship
    public function User() {
        return $this->belongsTo(User::class,'user_id', 'id');
    }

    public function product() {
        return $this->belongsTo(Product::class);
    }

}
