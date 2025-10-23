<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PronunciationHistory extends Model
{
    protected $table = 'pronunciation_history';

    protected $fillable = [
        'user_id',
        'card_id',
        'expected_text',
        'transcribed_text',
        'accuracy',
        'stars',
        'grade',
        'feedback',
    ];

    protected $casts = [
        'accuracy' => 'decimal:2',
        'stars' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship với User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship với Card
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /**
     * Lấy lịch sử theo user
     */
    public static function getByUser($userId, $limit = 10)
    {
        return self::where('user_id', $userId)
            ->with('card')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Lấy thống kê của user
     */
    public static function getUserStats($userId)
    {
        $history = self::where('user_id', $userId)->get();
        
        return [
            'total_practices' => $history->count(),
            'average_accuracy' => round($history->avg('accuracy'), 2),
            'best_score' => round($history->max('accuracy'), 2),
            'perfect_scores' => $history->where('accuracy', '>=', 95)->count(),
            'recent_improvement' => self::calculateImprovement($userId),
        ];
    }

    /**
     * Tính toán sự tiến bộ
     */
    private static function calculateImprovement($userId)
    {
        $recent = self::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->avg('accuracy');

        $previous = self::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->skip(5)
            ->limit(5)
            ->avg('accuracy');

        if ($previous == 0) {
            return 0;
        }

        return round(($recent - $previous), 2);
    }
}