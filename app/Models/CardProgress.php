<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardProgress extends Model
{
    protected $table = 'card_progress';
    
    protected $fillable = [
        'card_id',
        'user_id',
        'quality',
        'easiness',
        'repetition',
        'interval',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'easiness' => 'double',
        'quality' => 'integer',
        'repetition' => 'integer',
        'interval' => 'integer',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}