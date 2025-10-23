<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use OpenAI;

class PronunciationController extends Controller
{
    private $openai;

    public function __construct()
    {
        $this->openai = OpenAI::client(config('services.openai.api_key'));
    }

    /**
     * Nhận dạng giọng nói và đánh giá phát âm
     */
    public function evaluatePronunciation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'audio' => 'required|file|mimes:mp3,wav,m4a,webm|max:10240',
            'expected_text' => 'required|string|max:500',
            'card_id' => 'required|integer|exists:cards,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        try {
            // Lưu file audio tạm thời
            $audioFile = $request->file('audio');
            $tempPath = $audioFile->store('temp_audio', 'public');
            $fullPath = storage_path('app/public/' . $tempPath);

            // Gọi Whisper API để nhận dạng giọng nói
            $response = $this->openai->audio()->transcribe([
                'model' => 'whisper-1',
                'file' => fopen($fullPath, 'r'),
                'language' => 'en',
            ]);

            $transcribedText = $response->text;
            $expectedText = $request->input('expected_text');

            // Đánh giá độ chính xác
            $accuracy = $this->calculateAccuracy($transcribedText, $expectedText);
            
            // Phân tích chi tiết bằng GPT-4
            $detailedFeedback = $this->getDetailedFeedback($transcribedText, $expectedText);

            // Xóa file tạm
            Storage::disk('public')->delete($tempPath);

            // Lưu lịch sử luyện tập (tùy chọn)
            $this->savePracticeHistory($request->user()->id, $request->input('card_id'), $accuracy);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'transcribed_text' => $transcribedText,
                    'expected_text' => $expectedText,
                    'accuracy' => $accuracy,
                    'feedback' => $detailedFeedback,
                    'score' => $this->getScore($accuracy),
                ],
            ], 200);

        } catch (\Exception $e) {
            // Xóa file tạm nếu có lỗi
            if (isset($tempPath)) {
                Storage::disk('public')->delete($tempPath);
            }

            return response()->json([
                'error' => 'Lỗi khi xử lý audio',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tính độ chính xác phát âm (0-100)
     */
    private function calculateAccuracy($transcribed, $expected)
    {
        // Chuẩn hóa văn bản
        $transcribed = strtolower(trim($transcribed));
        $expected = strtolower(trim($expected));

        // Tính Levenshtein distance
        $distance = levenshtein($transcribed, $expected);
        $maxLength = max(strlen($transcribed), strlen($expected));

        if ($maxLength == 0) {
            return 100;
        }

        $similarity = (1 - ($distance / $maxLength)) * 100;
        return round(max(0, $similarity), 2);
    }

    /**
     * Lấy phản hồi chi tiết từ GPT-4
     */
    private function getDetailedFeedback($transcribed, $expected)
    {
        try {
            $response = $this->openai->chat()->create([
                'model' => 'gpt-4',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an English pronunciation expert. Analyze the pronunciation and provide constructive feedback in Vietnamese.'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Expected text: \"$expected\"\nTranscribed text: \"$transcribed\"\n\nProvide brief feedback (max 100 words) in Vietnamese about pronunciation quality, common mistakes, and tips for improvement."
                    ]
                ],
                'max_tokens' => 200,
                'temperature' => 0.7,
            ]);

            return $response->choices[0]->message->content;

        } catch (\Exception $e) {
            return 'Phát âm của bạn đã được ghi nhận. Hãy tiếp tục luyện tập!';
        }
    }

    /**
     * Chuyển độ chính xác thành điểm số và đánh giá
     */
    private function getScore($accuracy)
    {
        if ($accuracy >= 95) {
            return ['grade' => 'Xuất sắc', 'stars' => 5, 'color' => 'green'];
        } elseif ($accuracy >= 85) {
            return ['grade' => 'Tốt', 'stars' => 4, 'color' => 'lightgreen'];
        } elseif ($accuracy >= 70) {
            return ['grade' => 'Khá', 'stars' => 3, 'color' => 'orange'];
        } elseif ($accuracy >= 50) {
            return ['grade' => 'Trung bình', 'stars' => 2, 'color' => 'darkorange'];
        } else {
            return ['grade' => 'Cần cải thiện', 'stars' => 1, 'color' => 'red'];
        }
    }

    /**
     * Lưu lịch sử luyện tập
     */
    private function savePracticeHistory($userId, $cardId, $accuracy, $transcribed = '', $expected = '', $feedback = '', $score = [])
    {
        \App\Models\PronunciationHistory::create([
            'user_id' => $userId,
            'card_id' => $cardId,
            'expected_text' => $expected,
            'transcribed_text' => $transcribed,
            'accuracy' => $accuracy,
            'stars' => $score['stars'] ?? 1,
            'grade' => $score['grade'] ?? 'Cần cải thiện',
            'feedback' => $feedback,
        ]);
    }

    /**
     * Lấy lịch sử luyện tập của user
     */
    public function getHistory(Request $request)
    {
        $limit = $request->input('limit', 20);
        
        $history = \App\Models\PronunciationHistory::where('user_id', $request->user()->id)
            ->with(['card' => function($query) {
                $query->select('id', 'front', 'back', 'phonetic');
            }])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $history,
        ], 200);
    }

    /**
     * Lấy thống kê luyện phát âm
     */
    public function getStats(Request $request)
    {
        $stats = \App\Models\PronunciationHistory::getUserStats($request->user()->id);

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ], 200);
    }

    /**
     * Tạo audio phát âm chuẩn từ văn bản (sử dụng TTS)
     */
    public function generatePronunciation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'text' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        try {
            $response = $this->openai->audio()->speech([
                'model' => 'tts-1',
                'input' => $request->input('text'),
                'voice' => 'nova', // alloy, echo, fable, onyx, nova, shimmer
            ]);

            // Lưu file audio
            $fileName = 'pronunciation_' . time() . '.mp3';
            $path = 'pronunciations/' . $fileName;
            Storage::disk('public')->put($path, $response);

            $audioUrl = Storage::url($path);

            return response()->json([
                'status' => 'success',
                'audio_url' => $audioUrl,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Lỗi khi tạo audio',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}