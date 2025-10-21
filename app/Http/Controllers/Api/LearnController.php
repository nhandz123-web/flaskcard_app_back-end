<!-- 
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CardProgress;
use App\Models\Deck;
use App\Models\Card;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LearnController extends Controller
{
    public function getCardsToReview(Request $request, Deck $deck)
    {
        try {
            if (!$request->user()) {
                Log::error('No authenticated user found', ['deck_id' => $deck->id]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthenticated'
                ], 401, [], JSON_UNESCAPED_UNICODE);
            }

            Log::info('User ID: ' . $request->user()->id, [
                'deck_id' => $deck->id,
                'deck_user_id' => $deck->user_id
            ]);

            $this->authorize('view', $deck);

            DB::statement("SET time_zone = '+00:00';");
            $cacheKey = 'cards_to_review_' . $deck->id . '_' . $request->user()->id;
            $cards = Cache::remember($cacheKey, now()->addMinutes(2), function () use ($deck, $request) {
                return Card::where('deck_id', $deck->id)
                    ->where(function ($query) use ($request) {
                        $query->whereDoesntHave('progress', function ($q) use ($request) {
                            $q->where('user_id', $request->user()->id);
                        })->orWhereHas('progress', function ($q) use ($request) {
                            $q->where('user_id', $request->user()->id)
                              ->where('next_review_at', '<=', Carbon::now());
                        });
                    })
                    ->with(['progress' => function ($query) use ($request) {
                        $query->where('user_id', $request->user()->id);
                    }])
                    ->take(20)
                    ->get();
            });

            Log::debug('getCardsToReview response', [
                'deck_id' => $deck->id,
                'user_id' => $request->user()->id,
                'now' => Carbon::now()->toDateTimeString(),
                'cards' => $cards->toArray(),
            ]);

            return response()->json([
                'status' => 'success',
                'cards' => $cards
            ], 200, [], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            Log::error('Error in getCardsToReview: ' . $e->getMessage(), [
                'deck_id' => $deck->id,
                'user_id' => $request->user() ? $request->user()->id : 'No user',
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    public function updateProgress(Request $request, Deck $deck, Card $card)
    {
        try {
            DB::statement("SET time_zone = '+00:00';");
            $this->authorize('view', $deck);
            if ($card->deck_id != $deck->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Card does not belong to this deck'
                ], 403, [], JSON_UNESCAPED_UNICODE);
            }

            $data = $request->validate([
                'quality' => 'required|integer|min:0|max:5'
            ]);

            $progress = CardProgress::firstOrCreate(
                ['user_id' => $request->user()->id, 'card_id' => $card->id],
                ['repetition' => 0, 'interval' => 1, 'ease_factor' => 2.5]
            );

            $progress->repetition += 1;
            $progress->ease_factor = max(1.3, $progress->ease_factor + (0.1 - (5 - $data['quality']) * 0.08));
            $progress->interval = $data['quality'] < 3 ? 1 : $this->calculateInterval($progress);
            $progress->next_review_at = Carbon::now()->addDays($progress->interval);
            $progress->correct_count += $data['quality'] >= 3 ? 1 : 0;
            $progress->incorrect_count += $data['quality'] < 3 ? 1 : 0;
            $progress->save();

            // Đồng bộ với cards.next_review_date
            $card->update([
                'easiness' => $progress->ease_factor,
                'repetition' => $progress->repetition,
                'interval' => $progress->interval,
                'next_review_date' => $progress->next_review_at,
            ]);

            Cache::forget('cards_to_review_' . $deck->id . '_' . $request->user()->id);
            Cache::forget("cards_to_review_deck_{$deck->id}");

            Log::debug('Progress updated', [
                'card_id' => $card->id,
                'deck_id' => $deck->id,
                'user_id' => $request->user()->id,
                'progress' => $progress->toArray(),
                'card' => $card->toArray(),
            ]);

            return response()->json([
                'status' => 'success',
                'progress' => $progress
            ], 200, [], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            Log::error('Error in updateProgress: ' . $e->getMessage(), [
                'deck_id' => $deck->id,
                'card_id' => $card->id,
                'user_id' => $request->user() ? $request->user()->id : 'No user',
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    private function calculateInterval(CardProgress $progress)
    {
        if ($progress->repetition <= 1) return 1;
        if ($progress->repetition == 2) return 6;
        return (int)($progress->interval * $progress->ease_factor);
    }
} -->