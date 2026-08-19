<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string'], 'device_name' => ['nullable', 'string', 'max:100']]);
        $user = User::with('organization')->where('email', $credentials['email'])->first();
        if (! $user || ! Hash::check($credentials['password'], $user->password) || $user->status !== 'active') {
            return response()->json(['success' => false, 'message' => 'The supplied credentials are invalid.', 'code' => 'AUTH_INVALID_CREDENTIALS', 'errors' => null], 422);
        }
        $token = $user->createToken($credentials['device_name'] ?? 'nemtrack-mobile')->plainTextToken;
        return response()->json(['success' => true, 'message' => 'Signed in successfully.', 'data' => ['token' => $token, 'user' => $user]]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['success' => true, 'message' => 'Signed out successfully.', 'data' => null]);
    }
}
