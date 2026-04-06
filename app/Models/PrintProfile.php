<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintProfile extends Model
{
    protected $fillable = [
        'name', 'description', 'paper_size', 'orientation',
        'copies', 'duplex', 'default_printer', 'extra_options',
        'is_custom', 'custom_width', 'custom_height',
        'margin_top', 'margin_bottom', 'margin_left', 'margin_right',
    ];

    protected $casts = [
        'extra_options' => 'array',
        'is_custom' => 'boolean',
        'custom_width' => 'float',
        'custom_height' => 'float',
        'margin_top' => 'float',
        'margin_bottom' => 'float',
        'margin_left' => 'float',
        'margin_right' => 'float',
    ];
}
