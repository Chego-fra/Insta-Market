<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\ModelHelpers\ProductModelHelpers;
use App\Traits\Relationships\ProductRelationships;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;
    use ProductModelHelpers;
    use ProductRelationships;

    protected $fillable = [
        'title',
        'description',
        'price',
        'image_path',
        'video_path'
    ];

    protected $casts = [
        'title' => 'string',
        'description' => 'string',
        'price' => 'decimal:2',
        'image_path' => 'string',
        'video_path' => 'string'
    ];
}
