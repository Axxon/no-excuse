<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['type', 'metadata', 'created_at'];

    protected $hidden = ['id', 'application_id'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }
}
