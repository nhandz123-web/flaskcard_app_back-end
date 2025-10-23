<?php

namespace App\Services;

use App\Models\Card;
use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ForgettingCurveService
{
    /**
     * Dự đoán khả năng quên của một thẻ dựa trên lịch sử học tập
     */
    public function predictForgettingProbability(Card $card, array $reviewHistory = []): array
    {
        // Tạo cache key dựa trên card ID và thời gian cập nhật
        $cacheKey = "forgetting_prediction_{$card->id}_{$card->updated_at->timestamp}";

        // Kiểm tra cache trước (cache 1 giờ)
        return Cache::remember($cacheKey, 3600, function () use ($card, $reviewHistory) {
            try {
                $prompt = $this->buildPrompt($card, $reviewHistory);

                $response = OpenAI::chat()->create([
                    'model' => config('openai.model', 'gpt-4o-mini'),
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are an expert in memory retention and spaced repetition algorithms. Analyze learning patterns and predict forgetting probability.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.3, // Thấp hơn để có kết quả nhất quán
                    'max_tokens' => 500,
                ]);

                $aiResponse = $response->choices[0]->message->content;

                return $this->parseAIResponse($aiResponse, $card);
            } catch (\Exception $e) {
                Log::error('AI Prediction Error', [
                    'card_id' => $card->id,
                    'error' => $e->getMessage()
                ]);

                // Fallback về thuật toán SM-2 truyền thống
                return $this->fallbackPrediction($card);
            }
        });
    }

    /**
     * Xây dựng prompt cho OpenAI
     */
    private function buildPrompt(Card $card, array $reviewHistory): string
    {
        $historyText = $this->formatReviewHistory($reviewHistory);

        return <<<PROMPT
PROMPT;
    }

    /**
     * Format lịch sử ôn tập
     */
    private function formatReviewHistory(array $reviewHistory): string
    {
        if (empty($reviewHistory)) {
            return "No review history available.";
        }

        $formatted = [];
        foreach ($reviewHistory as $review) {
            $quality = $this->qualityToText($review['quality'] ?? 0);
            $date = $review['reviewed_at'] ?? 'Unknown';
            $formatted[] = "- {$date}: Quality {$review['quality']}/5 ({$quality})";
        }

        return implode("\n", $formatted);
    }

    /**
     * Chuyển quality score thành text
     */
    private function qualityToText(int $quality): string
    {
        return match ($quality) {
            0 => 'Complete blackout',
            1 => 'Incorrect, but familiar',
            2 => 'Incorrect, but easy to recall',
            3 => 'Correct with difficulty',
            4 => 'Correct with hesitation',
            5 => 'Perfect recall',
            default => 'Unknown'
        };
    }

    /**
     * Parse phản hồi từ AI
     */
    private function parseAIResponse(string $response, Card $card): array
    {
        // Trích xuất JSON từ response
        preg_match('/\{[^}]+\}/', $response, $matches);

        if (empty($matches)) {
            Log::warning('Could not parse AI response', ['response' => $response]);
            return $this->fallbackPrediction($card);
        }

        try {
            $data = json_decode($matches[0], true);

            // Validate và normalize data
            return [
                'forgetting_probability' => max(0, min(100, $data['forgetting_probability'] ?? 50)),
                'recommended_interval' => max(1, min(180, $data['recommended_interval'] ?? $card->interval)),
                'difficulty' => in_array($data['difficulty'] ?? '', ['Easy', 'Medium', 'Hard'])
                    ? $data['difficulty']
                    : 'Medium',
                'confidence' => max(0, min(100, $data['confidence'] ?? 50)),
                'reasoning' => $data['reasoning'] ?? 'AI prediction based on learning patterns.',
                'ai_powered' => true,
                'timestamp' => now()->toIso8601String()
            ];
        } catch (\Exception $e) {
            Log::error('JSON parse error', ['error' => $e->getMessage()]);
            return $this->fallbackPrediction($card);
        }
    }

    /**
     * Thuật toán dự phòng khi AI không khả dụng
     */
    private function fallbackPrediction(Card $card): array
    {
        // Dựa trên easiness factor và repetition
        $forgettingProb = 100 - (($card->easiness - 1.3) / 1.2 * 50) - ($card->repetition * 5);
        $forgettingProb = max(10, min(90, $forgettingProb));

        return [
            'forgetting_probability' => round($forgettingProb),
            'recommended_interval' => $card->interval,
            'difficulty' => $card->easiness < 2.0 ? 'Hard' : ($card->easiness > 2.3 ? 'Easy' : 'Medium'),
            'confidence' => 60,
            'reasoning' => 'Fallback prediction based on SM-2 algorithm.',
            'ai_powered' => false,
            'timestamp' => now()->toIso8601String()
        ];
    }

    /**
     * Lấy thống kê tổng hợp cho nhiều thẻ
     */
    public function getBatchPredictions(array $cardIds, int $userId): array
    {
        $cards = Card::whereIn('id', $cardIds)
            ->whereHas('deck', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->get();

        $predictions = [];
        foreach ($cards as $card) {
            $predictions[$card->id] = $this->predictForgettingProbability($card);
        }

        return $predictions;
    }
}
