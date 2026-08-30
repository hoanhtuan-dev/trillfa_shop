<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudioApiKey extends Model
{
    protected $table = 'studio_api_keys';
    protected $fillable = ['provider', 'label', 'value', 'kind', 'scopes', 'priority', 'enabled', 'note'];
    protected $casts = ['enabled' => 'boolean', 'scopes' => 'array'];
}
