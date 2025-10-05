<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    // POST /api/register
    public function register(Request $request)
    {
        $data = Validator::make($request->all(), [
            'name'     => ['required','string','max:255'],
            'email'    => ['required','email','max:255','unique:users,email'],
            'password' => ['required','string','min:8'],
        ])->validate();

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // ✅ LẤY CHUỖI TOKEN (Passport)
        $accessToken = $user->createToken('api-token')->accessToken;

        return response()->json([
            'status'       => 'success',
            'message'      => 'Đăng ký thành công',
            'token_type'   => 'Bearer',
            'access_token' => $accessToken,
            'user'         => ['id'=>$user->id,'name'=>$user->name,'email'=>$user->email],
        ], 201, [], JSON_UNESCAPED_UNICODE);
    }

    // POST /api/login
    public function login(Request $request)
    {
        $data = Validator::make($request->all(), [
            'email'    => ['required','email'],
            'password' => ['required','string','min:8'],
        ])->validate();

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Email hoặc mật khẩu không đúng'
            ], 401, [], JSON_UNESCAPED_UNICODE);
        }

        // ✅ LẤY CHUỖI TOKEN (Passport)
        $accessToken = $user->createToken('api-token')->accessToken;

        return response()->json([
            'status'       => 'success',
            'message'      => 'Đăng nhập thành công',
            'token_type'   => 'Bearer',
            'access_token' => $accessToken,
            'user'         => ['id'=>$user->id,'name'=>$user->name,'email'=>$user->email],
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // GET /api/me  (cần Authorization: Bearer <token>)
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    // POST /api/logout  (cần Authorization: Bearer <token>)
    public function logout(Request $request)
    {
        // Hủy tất cả tokens của user (an toàn, đơn giản)
        $request->user()->tokens()->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Đã đăng xuất'
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
