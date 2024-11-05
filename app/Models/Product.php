<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use hasFactory;

    protected $fillable = [
        'id',
        'name',
        'slug',
        'short_desription',
        'description',
        'regular_price',
        'sale_price',
        'SKU',
        'stock_status',
        'featured',
        'quantity',
        'image',
        'images',
        'category_id',
        'brand_id'
    ];

    public function category() {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function brand() {
        return $this->belongsTo(Brand::class, 'brand_id');
    }
}
