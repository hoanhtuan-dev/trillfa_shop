<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Generation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'project_id', 'prompts_history_id', 'type', 'status',
        'prompt', 'media_url', 'base_image', 'mask_image', 'job_id', 'error', 'credits_cost',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function promptsHistory(): BelongsTo
    {
        return $this->belongsTo(PromptsHistory::class);
    }
}