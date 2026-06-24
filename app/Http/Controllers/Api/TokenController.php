<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TokenController extends Controller
{
    /**
     * 创建 API Token（登录）
     *
     * @bodyParam email string required 用户邮箱
     * @bodyParam password string required 用户密码
     * @bodyParam name string required Token 名称（用于识别）
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'name' => 'required|string|max:255',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['邮箱或密码不正确'],
            ]);
        }

        // Revoke old tokens with the same name to avoid duplicates
        $user->tokens()->where('name', $request->name)->delete();

        $token = $user->createToken($request->name);

        return ApiResponse::created([
            'token' => $token->plainTextToken,
            'token_name' => $request->name,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], 'Token 创建成功');
    }

    /**
     * 列出当前用户的所有 Token
     */
    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()->get(['id', 'name', 'created_at', 'last_used_at']);

        return ApiResponse::success($tokens);
    }

    /**
     * 撤销 Token
     *
     * @urlParam tokenId int required Token ID
     */
    public function destroy(Request $request, int $tokenId): JsonResponse
    {
        $deleted = $request->user()->tokens()->where('id', $tokenId)->delete();

        if (! $deleted) {
            return ApiResponse::error('Token 不存在', 404);
        }

        return ApiResponse::noContent('Token 已撤销');
    }
}
