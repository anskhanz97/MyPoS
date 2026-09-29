<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryPublicId;
use Database\Factories\ProductImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['merchant_id', 'product_id', 'disk', 'path', 'mime_type', 'size_bytes', 'alt_text', 'sort_order'])]
class ProductImage extends Model
{
    /** @use HasFactory<ProductImageFactory> */
    use HasBinaryPublicId, HasFactory;
}
