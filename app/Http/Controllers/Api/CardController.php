<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Deck;
use App\Models\Card;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log; // Import Log facade
use Carbon\Carbon; // Thêm import Carbon
use App\Services\ForgettingCurveService;
use App\Models\CardProgress;

class CardController extends Controller
{
    private function assertOwner(Request $request, Deck $deck)
    {
        abort_unless($deck->user_id === $request->user()->id, 403, 'Không có quyền');
    }

    public function index(Request $request, Deck $deck)
    {
        $this->assertOwner($request, $deck);
        $cards = $deck->cards()->latest()->paginate(20);
        return response()->json($cards);
    }

    public function store(Request $request, Deck $deck)
    {
        $this->assertOwner($request, $deck);

        $data = Validator::make($request->all(), [
            'front' => ['required', 'string', 'max:255'],
            'back' => ['required', 'string'],
            'phonetic' => ['nullable', 'string', 'max:255'],
            'example' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url'],
            'audio_url' => ['nullable', 'url'],
            'extra' => ['nullable', 'array'],
        ])->validate();

        $card = $deck->cards()->create($data);

        // Tăng cards_count
        $deck->increment('cards_count');

        return response()->json(['status' => 'success', 'card' => $card], 201, [], JSON_UNESCAPED_UNICODE);
    }

    public function show(Request $request, Deck $deck, Card $card)
    {
        $this->assertOwner($request, $deck);
        abort_unless($card->deck_id === $deck->id, 404);
        return response()->json($card);
    }

    public function update(Request $request, Deck $deck, Card $card)
    {
        $this->assertOwner($request, $deck);
        abort_unless($card->deck_id === $deck->id, 404);

        $data = Validator::make($request->all(), [
            'front' => ['sometimes', 'string', 'max:255'],
            'back' => ['sometimes', 'string'],
            'phonetic' => ['nullable', 'string', 'max:255'],
            'example' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url'],
            'audio_url' => ['nullable', 'url'],
            'extra' => ['nullable', 'array'],
        ])->validate();

        $card->update($data);
        return response()->json(['status' => 'success', 'card' => $card], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function destroy(Request $request, Deck $deck, Card $card)
    {
        $this->assertOwner($request, $deck);
        abort_unless($card->deck_id === $deck->id, 404);

        if ($card->image_url && Storage::exists(str_replace('/storage', 'public', $card->image_url))) {
            Storage::delete(str_replace('/storage', 'public', $card->image_url));
        }

        if ($card->audio_url && Storage::exists(str_replace('/storage', 'public', $card->audio_url))) {
            Storage::delete(str_replace('/storage', 'public', $card->audio_url));
        }

        $card->delete();

        // Giảm cards_count
        $deck->decrement('cards_count');

        return response()->json(['status' => 'success', 'message' => 'Đã xoá thẻ'], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function uploadImage(Request $request, int $cardId)
    {
        $card = Card::findOrFail($cardId);
        $this->assertOwner($request, $card->deck); // Kiểm tra quyền

        $request->validate(['image' => 'required|image|mimes:jpeg,png|max:2048']);

        if ($card->image_url) {
            Storage::disk('public')->delete(str_replace('storage/', '', $card->image_url));
        }

        $path = $request->file('image')->store('card_images', 'public');
        $imageUrl = Storage::url($path);
        $card->update(['image_url' => $imageUrl]);

        return response()->json(['image_url' => $card->image_url], 200);
    }

    public function uploadAudio(Request $request, int $cardId)
    {
        $card = Card::findOrFail($cardId);
        $this->assertOwner($request, $card->deck);

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:mp3,wav,ogg|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $file = $request->file('file');
        if ($file) {
            if ($card->audio_url) {
                Storage::disk('public')->delete(str_replace('storage/', '', $card->audio_url));
            }

            $fileName = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('uploads/audio', $fileName, 'public');
            $audioUrl = Storage::url($path);
            $card->update(['audio_url' => $audioUrl]);

            return response()->json([
                'status' => 'success',
                'audio_url' => $audioUrl,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json(['error' => 'No file uploaded'], 400);
    }

    public function updateCardDetails(Request $request, $deckId, Card $card)
    {
        if ($card->deck_id != $deckId) {
            return response()->json(['error' => 'Card does not belong to this deck'], 403);
        }

        $this->assertOwner($request, $card->deck);

        $request->validate([
            'front' => 'required|string',
            'back' => 'required|string',
            'phonetic' => 'nullable|string',
            'example' => 'nullable|string',
            'imageUrl' => 'nullable|string',
            'audioUrl' => 'nullable|string',
            'extra' => 'nullable|array',
        ]);

        $card->update([
            'front' => $request->input('front'),
            'back' => $request->input('back'),
            'phonetic' => $request->input('phonetic', $card->phonetic),
            'example' => $request->input('example', $card->example),
            'image_url' => $request->input('imageUrl', $card->image_url),
            'audio_url' => $request->input('audioUrl', $card->audio_url),
            'extra' => $request->input('extra', $card->extra),
        ]);

        return response()->json(['message' => 'Card updated successfully', 'card' => $card], 200);
    }

    public function markCardReview(Request $request, int $cardId)
    {
        try {
            $card = Card::findOrFail($cardId);
            $this->assertOwner($request, $card->deck);

            Log::debug('markCardReview input', [
                'card_id' => $cardId,
                'request_data' => $request->all(),
                'received_at' => now()->toDateTimeString(),
            ]);

            $validator = Validator::make($request->all(), [
                'quality' => 'required|integer|min:0|max:5',
                'easiness' => 'required|numeric|min:1.3',
                'repetition' => 'required|integer|min:0',
                'interval' => 'required|integer|min:1',
                'next_review_date' => 'required|date',
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed for markCardReview', [
                    'card_id' => $cardId,
                    'errors' => $validator->errors()->toArray(),
                ]);
                return response()->json(['error' => $validator->errors()], 422);
            }

            $nextReviewDate = Carbon::parse($request->next_review_date)->utc();

            // Lưu vào card_progress để theo dõi lịch sử
            CardProgress::create([
                'card_id' => $cardId,
                'user_id' => $request->user()->id,
                'quality' => $request->quality,
                'easiness' => $request->easiness,
                'repetition' => $request->repetition,
                'interval' => $request->interval,
                'reviewed_at' => now(),
            ]);

            $card->update([
                'easiness' => $request->easiness,
                'repetition' => $request->repetition,
                'interval' => $request->interval,
                'next_review_date' => $nextReviewDate,
            ]);

            Log::debug('Card updated', [
                'card_id' => $cardId,
                'updated_data' => $card->toArray(),
                'updated_at' => now()->toDateTimeString(),
            ]);

            return response()->json(['status' => 'success'], 200);
        } catch (\Exception $e) {
            Log::error('Error in markCardReview', [
                'card_id' => $cardId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Server error'], 500);
        }
    }

    public function getCardsToReview(Request $request, $deckId)
    {
        try {
            $deck = Deck::findOrFail($deckId);
            $this->assertOwner($request, $deck);

            $now = now()->setTimezone('Asia/Ho_Chi_Minh'); // Sử dụng múi giờ +07:00
            $cards = Card::where('deck_id', $deckId)
                ->where(function ($query) use ($now) {
                    $query->whereNull('next_review_date')
                        ->orWhere('next_review_date', '<=', $now);
                })
                ->get();

            Log::debug('getCardsToReview response', [
                'deck_id' => $deckId,
                'now_local' => $now->toDateTimeString(),
                'card_count' => $cards->count(),
                'cards' => $cards->toArray(),
            ]);

            return response()->json(['cards' => $cards], 200);
        } catch (\Exception $e) {
            Log::error('Error in getCardsToReview', [
                'deck_id' => $deckId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    private ForgettingCurveService $forgettingCurveService;

    public function __construct(ForgettingCurveService $forgettingCurveService)
    {
        $this->forgettingCurveService = $forgettingCurveService;
    }

    /**
     * Lấy dự đoán AI cho một thẻ
     */
    public function getAIPrediction(Request $request, int $cardId)
    {
        $card = Card::findOrFail($cardId);
        $this->assertOwner($request, $card->deck);

        // Lấy lịch sử ôn tập (nếu có bảng card_progress)
        $reviewHistory = $card->progress()
            ->orderBy('reviewed_at', 'desc')
            ->limit(10)
            ->get(['quality', 'reviewed_at'])
            ->map(fn($p) => [
                'quality' => $p->quality,
                'reviewed_at' => $p->reviewed_at->format('Y-m-d H:i')
            ])
            ->toArray();

        $prediction = $this->forgettingCurveService->predictForgettingProbability(
            $card,
            $reviewHistory
        );

        return response()->json([
            'status' => 'success',
            'card_id' => $cardId,
            'prediction' => $prediction
        ], 200);
    }

    /**
     * Lấy dự đoán cho nhiều thẻ trong deck
     */
    public function getDeckAIPredictions(Request $request, int $deckId)
    {
        $deck = Deck::findOrFail($deckId);
        $this->assertOwner($request, $deck);

        $cardIds = $deck->cards()->pluck('id')->toArray();

        $predictions = $this->forgettingCurveService->getBatchPredictions(
            $cardIds,
            $request->user()->id
        );

        // Tính thống kê tổng hợp
        $stats = [
            'total_cards' => count($predictions),
            'high_risk_cards' => collect($predictions)->filter(fn($p) => $p['forgetting_probability'] > 70)->count(),
            'medium_risk_cards' => collect($predictions)->filter(fn($p) => $p['forgetting_probability'] >= 40 && $p['forgetting_probability'] <= 70)->count(),
            'low_risk_cards' => collect($predictions)->filter(fn($p) => $p['forgetting_probability'] < 40)->count(),
            'average_forgetting_probability' => round(collect($predictions)->avg('forgetting_probability'), 1),
        ];

        return response()->json([
            'status' => 'success',
            'deck_id' => $deckId,
            'predictions' => $predictions,
            'statistics' => $stats
        ], 200);
    }

    /**
     * Cải tiến thuật toán review với AI
     */
    public function markCardReviewWithAI(Request $request, int $cardId)
    {
        try {
            $card = Card::findOrFail($cardId);
            $this->assertOwner($request, $card->deck);

            $validator = Validator::make($request->all(), [
                'quality' => 'required|integer|min:0|max:5',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }

            // Lấy dự đoán AI
            $reviewHistory = $card->progress()
                ->orderBy('reviewed_at', 'desc')
                ->limit(10)
                ->get(['quality', 'reviewed_at'])
                ->toArray();

            $aiPrediction = $this->forgettingCurveService->predictForgettingProbability(
                $card,
                $reviewHistory
            );

            // Kết hợp SM-2 với AI prediction
            $quality = $request->input('quality');
            $easiness = $card->easiness ?? 2.5;
            $repetition = $card->repetition ?? 0;

            // Tính toán easiness theo SM-2
            $easiness = $easiness + (0.1 - (5 - $quality) * (0.08 + (5 - $quality) * 0.02));
            $easiness = max(1.3, min(2.5, $easiness));

            // Xác định interval dựa trên quality
            $baseInterval = match ($quality) {
                0 => 1 / 1440, // Học lại: 1 phút (1/1440 ngày)
                1 => 5 / 1440, // Khó: 5 phút (5/1440 ngày)
                2 => 5 / 1440, // Khó: 5 phút (5/1440 ngày)
                3 => 6 / 24,   // Bình thường: 6 giờ (6/24 ngày)
                4 => 1,        // Dễ: 1 ngày
                5 => 1,        // Dễ: 1 ngày
                default => 1   // Mặc định: 1 ngày
            };

            // Kết hợp với AI recommendation
            $aiInterval = $aiPrediction['recommended_interval'];
            $finalInterval = (int)(($baseInterval * 0.7) + ($aiInterval * 0.3)); // 70% SM-2, 30% AI
            $finalInterval = max(1 / 1440, min(180, $finalInterval)); // Giới hạn từ 1 phút đến 180 ngày

            // Tính toán next_review_date dựa trên finalInterval
            $nextReviewDate = now()->addMinutes($finalInterval * 1440); // Chuyển đổi ngày sang phút

            if ($quality >= 3) {
                $repetition += 1;
            } else {
                $repetition = max(0, $repetition - 1);
            }

            $card->update([
                'easiness' => $easiness,
                'repetition' => $repetition,
                'interval' => $finalInterval,
                'next_review_date' => $nextReviewDate,
            ]);

            Log::debug('Card reviewed with AI', [
                'card_id' => $cardId,
                'quality' => $quality,
                'sm2_interval' => $baseInterval,
                'ai_interval' => $aiInterval,
                'final_interval' => $finalInterval,
                'ai_forgetting_prob' => $aiPrediction['forgetting_probability']
            ]);

            return response()->json([
                'status' => 'success',
                'card' => $card,
                'ai_insights' => [
                    'forgetting_probability' => $aiPrediction['forgetting_probability'],
                    'difficulty' => $aiPrediction['difficulty'],
                    'reasoning' => $aiPrediction['reasoning']
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error in markCardReviewWithAI', [
                'card_id' => $cardId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Server error'], 500);
        }
    }
}
