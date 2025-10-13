<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Deck;
use App\Events\DeckUpdated;
use App\Events\DeckDeleted;

class DeckController extends Controller
{
    public function index(Request $request)
    {
        $decks = Deck::where('user_id', $request->user()->id)
            ->latest()->paginate(10);
        return response()->json($decks);
    }

    public function store(Request $request)
    {
        $data = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ])->validate();

        $deck = Deck::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        broadcast(new DeckUpdated($deck));

        return response()->json(['status' => 'success', 'deck' => $deck], 201, [], JSON_UNESCAPED_UNICODE);
    }

    public function show(Request $request, $deckId)
    {
        $deck = Deck::findOrFail($deckId);
        $this->authorizeDeck($request, $deck);
        return response()->json($deck);
    }

    public function update(Request $request, $deckId)
    {
        $deck = Deck::findOrFail($deckId);
        $this->authorize('update', $deck);

        $data = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ])->validate();

        $deck->update($data);

        broadcast(new DeckUpdated($deck));

        return response()->json(['status' => 'success', 'deck' => $deck], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function destroy(Request $request, $deckId)
    {
        $deck = Deck::findOrFail($deckId);
        $this->authorize('delete', $deck);

        $deck->cards()->delete();
        $deck->delete();

        broadcast(new DeckDeleted($deck));

        return response()->json(['status' => 'success', 'message' => 'Đã xoá deck'], 200, [], JSON_UNESCAPED_UNICODE);
    }

    private function authorizeDeck(Request $request, Deck $deck)
    {
        if ($deck->user_id !== $request->user()->id) {
            abort(403, 'Không có quyền truy cập deck này');
        }
    }
}
