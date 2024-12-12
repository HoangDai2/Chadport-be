<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';
    protected $primaryKey = 'id';

    protected $fillable = [
        'category_id',      // Foreign key to categories table
        'brand_id',         // Foreign key to brands table
        'name',
        'description',
        'title',
        'status',           // Enum field for status
        'price',
        'price_sale',
        'total_quatity',
        'image_product',
        'image_description', // JSON field for image descriptions
        'created_at',
        'updated_at',
    ];


    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id'); // Each product belongs to a category
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id'); // Each product belongs to a brand
    }

    public function variants()
    {
        return $this->hasMany(ProductItems::class, 'product_id'); // A product can have multiple product items
    }

    public function productImages()
    {
        return $this->hasMany(ProductImage::class, 'product_id'); // Assuming you have a ProductImage model for images
    }
    
    public function productItem() {
        return $this->belongsTo(ProductItems::class, 'product_item_id');
    }
}