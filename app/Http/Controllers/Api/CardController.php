<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Deck;
use App\Models\Card;
use Illuminate\Support\Facades\Storage;

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
        $card = Card::findOrFail($cardId);
        $this->assertOwner($request, $card->deck);

        $validator = Validator::make($request->all(), [
            'quality' => 'required|integer|min:0|max:5',
            'easiness' => 'required|numeric|min:1.3',
            'repetition' => 'required|integer|min:0',
            'interval' => 'required|integer|min:1',
            'next_review_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $card->update([
            'easiness' => $request->easiness,
            'repetition' => $request->repetition,
            'interval' => $request->interval,
            'next_review_date' => $request->next_review_date,
        ]);

        return response()->json(['status' => 'success'], 200);
    }

    public function getCardsToReview(Request $request, $deckId)
    {
        $deck = Deck::findOrFail($deckId);
        $this->assertOwner($request, $deck);

        $cards = Card::where('deck_id', $deckId)
            ->where(function ($query) {
                $query->whereNull('next_review_date')
                    ->orWhere('next_review_date', '<=', now());
            })
            ->get();

        return response()->json(['cards' => $cards], 200);
    }
}
