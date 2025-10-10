<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Card extends Model
{
    protected $fillable = [
        'deck_id',
        'front',
        'back',
        'phonetic',
        'example',
        'image_url',
        'audio_url',
        'extra',
        'easiness',
        'repetition',
        'interval',
        'next_review_date',
    ];

    protected $casts = ['extra' => 'array'];

    public function deck()
    {
        return $this->belongsTo(Deck::class);
    }

    public function progress()
    {
        return $this->hasMany(CardProgress::class);
    }

    public function setImageUrlAttribute($value)
    {
        // Xóa hình ảnh cũ nếu có và giá trị mới là null
        if ($this->image_url && is_null($value)) {
            Storage::disk('public')->delete(str_replace('storage/', '', $this->image_url));
        }
        $this->attributes['image_url'] = $value;
    }

    public function setAudioUrlAttribute($value)
    {
        // Xóa audio cũ nếu có và giá trị mới là null
        if ($this->audio_url && is_null($value)) {
            Storage::disk('public')->delete(str_replace('storage/', '', $this->audio_url));
        }
        $this->attributes['audio_url'] = $value;
    }
}
