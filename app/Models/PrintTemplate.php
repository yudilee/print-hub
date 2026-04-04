<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintTemplate extends Model
{
    protected $fillable = [
        'name',
        'paper_width_mm',
        'paper_height_mm',
        'background_image_path',
        'elements'
    ];

    protected $casts = [
        'elements' => 'array',
    ];
}
