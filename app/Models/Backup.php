<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    protected $fillable = ['filename', 'size', 'note'];

    protected $casts = [
        'size' => 'integer',
    ];
}
