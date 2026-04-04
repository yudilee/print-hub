<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintProfile extends Model
{
    protected $fillable = [
        'name', 'description', 'paper_size', 'orientation',
        'copies', 'duplex', 'default_printer', 'extra_options',
    ];

    protected $casts = [
        'extra_options' => 'array',
    ];
}
