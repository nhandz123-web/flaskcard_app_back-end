<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserFlashcardStat extends Model
{
    protected $fillable = ['user_id', 'flashcards_learned', 'words_mastered'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}