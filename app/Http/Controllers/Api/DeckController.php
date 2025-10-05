<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Deck;

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
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ])->validate();

        $deck = Deck::create([
            'user_id'     => $request->user()->id,   // lấy từ token Passport
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        return response()->json(['status' => 'success', 'deck' => $deck], 201, [], JSON_UNESCAPED_UNICODE);
    }

    public function show(Request $request, Deck $deck)
    {
        $this->authorizeDeck($request, $deck);
        return response()->json($deck);
    }

    public function update(Request $request, Deck $deck)
    {
        $this->authorize('update', $deck); // Thay authorizeDeck bằng authorize với Policy

        $data = Validator::make($request->all(), [
            'name'        => ['sometimes', 'string', 'max:255'], // Đồng bộ với title
            'description' => ['nullable', 'string'],
        ])->validate();

        $deck->update($data);
        return response()->json(['status' => 'success', 'deck' => $deck], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function destroy(Request $request, Deck $deck)
    {
        $this->authorize('delete', $deck); // Thay authorizeDeck bằng authorize với Policy
        $deck->cards()->delete(); // Xóa cards liên quan (tùy chọn)
        $deck->delete();
        return response()->json(['status' => 'success', 'message' => 'Đã xoá deck'], 200, [], JSON_UNESCAPED_UNICODE);
    }

    private function authorizeDeck(Request $request, Deck $deck)
    {
        abort_unless($deck->user_id === $request->user()->id, 403, 'Không có quyền');
    }
}
