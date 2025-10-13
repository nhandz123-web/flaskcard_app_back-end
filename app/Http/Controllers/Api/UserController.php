<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function getProfile(Request $request)
    {
        $user = Auth::user();
        $stats = $user->flashcardStats ?? ['flashcards_learned' => 0, 'words_mastered' => 0];

        return response()->json([
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
            'created_at' => $user->created_at->format('d/m/Y'),
            'last_login' => $user->last_login ? $user->last_login->format('d/m/Y h:i A') : null,
            'stats' => [
                'flashcards_learned' => $stats['flashcards_learned'],
                'words_mastered' => $stats['words_mastered'],
            ],
        ], 200);
    }
}