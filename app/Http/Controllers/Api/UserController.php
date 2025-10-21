<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function getProfile(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

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

    public function updateProfile(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
        ]);

        try {
            $user->update($validated);
            return response()->json([
                'message' => 'Cập nhật thông tin thành công',
                'user' => $user
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Không thể cập nhật thông tin',
                'details' => $e->getMessage()
            ], 500);
        }
    }


    public function updateAvatar(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        // Xác thực file ảnh
        /** @var \App\Models\User $user */
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        // Xóa ảnh cũ nếu tồn tại
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Lưu ảnh mới
        $path = $request->file('avatar')->store('avatars', 'public');
        $user->avatar = $path;
        $user->save();

        return response()->json(['avatar_url' => asset('storage/' . $path)], 200);
    }
}
