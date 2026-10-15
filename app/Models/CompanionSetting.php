<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanionSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'companion_name',
        'ai_model',
        'system_prompt',
        'live2d_model_path',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
