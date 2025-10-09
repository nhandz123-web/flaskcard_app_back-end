<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CardProgress extends Model
{
    protected $table = 'card_progresses';
    protected $fillable = [
        'user_id', 'card_id', 'repetition', 'interval', 'ease_factor', 'next_review_at', 'correct_count', 'incorrect_count',
    ];
    protected $casts = [
        'next_review_at' => 'datetime',
        'ease_factor' => 'float',
        'repetition' => 'integer',
        'interval' => 'integer',
        'correct_count' => 'integer',
        'incorrect_count' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function card()
    {
        return $this->belongsTo(Card::class);
    }
}