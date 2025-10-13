<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = Validator::make($request->all(), [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ])->validate();

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $accessToken = $user->createToken('api-token')->accessToken;

        return response()->json([
            'status'       => 'success',
            'message'      => 'Đăng ký thành công',
            'token_type'   => 'Bearer',
            'access_token' => $accessToken,
            'user'         => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ], 201, [], JSON_UNESCAPED_UNICODE);
    }

    public function login(Request $request)
    {
        $data = Validator::make($request->all(), [
            'email'    => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
        ])->validate();

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Email hoặc mật khẩu không đúng'
            ], 401, [], JSON_UNESCAPED_UNICODE);
        }

        $user->last_login = now();
        $user->save();

        $accessToken = $user->createToken('api-token')->accessToken;

        return response()->json([
            'status'       => 'success',
            'message'      => 'Đăng nhập thành công',
            'token_type'   => 'Bearer',
            'access_token' => $accessToken,
            'user'         => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $stats = $user->flashcardStats ? [
            'flashcards_learned' => $user->flashcardStats->flashcards_learned,
            'words_mastered' => $user->flashcardStats->words_mastered,
        ] : [
            'flashcards_learned' => 0,
            'words_mastered' => 0,
        ];

        return response()->json([
            'id' => $user->id, // Thêm id
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
            'created_at' => $user->created_at->format('d/m/Y'),
            'last_login' => $user->last_login ? $user->last_login->format('d/m/Y h:i A') : null,
            'stats' => $stats,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Đã đăng xuất'
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}