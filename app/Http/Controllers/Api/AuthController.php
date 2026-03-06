<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\admin\RegistationAuthor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // --------------------------------------
    // Register User
    // --------------------------------------
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $user = User::create([
                'firstname' => $request->firstname,
                'lastname'  => $request->lastname,
                'email'     => $request->email,
                'password'  => $request->password, // auto-hashed by model
                'role_id'   => $request->role_id,
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return $this->successResponse([
                'user'  => $user,
                'token' => $token,
            ], 'User registered successfully', 201);

        } catch (\Exception $e) {
            return $this->errorResponse('Registration failed', $e->getMessage(), 500);
        }
    }

    // --------------------------------------
    // Register Author
    // --------------------------------------
    public function authorRegister(RegistationAuthor $request): JsonResponse
    {
        return $this->register($request);
    }

    // --------------------------------------
    // Login
    // --------------------------------------
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->errorResponse('Invalid credentials', 'Email or password is incorrect', 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'user'  => $user,
            'token' => $token,
        ], 'Login successful', 200);
    }

    // --------------------------------------
    // Logout
    // --------------------------------------
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Logout successful', 200);
    }

    // --------------------------------------
    // Request Password Reset
    // --------------------------------------
    public function requestPasswordReset(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $user = User::where('email', $request->email)->first();
        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'email' => $user->email,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // TODO: Send $token via email

        return $this->successResponse(null, 'Password reset link sent to your email', 200);
    }

    // --------------------------------------
    // Reset Password
    // --------------------------------------
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$resetRecord || !Hash::check($request->token, $resetRecord->token)) {
            return $this->errorResponse('Invalid token', 'The reset token is invalid or expired', 400);
        }

        if (now()->diffInHours($resetRecord->created_at) > 1) {
            return $this->errorResponse('Expired token', 'The reset token has expired', 400);
        }

        $user = User::where('email', $request->email)->first();
        $user->update(['password' => $request->password]);

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return $this->successResponse(null, 'Password reset successfully', 200);
    }

    // --------------------------------------
    // Get Current User (Dashboard API)
    // --------------------------------------
    public function getCurrentUser(Request $request): JsonResponse
    {
        return $this->successResponse(
            $request->user(),
            'User retrieved successfully',
            200
        );
    }

    // --------------------------------------
    // Update Profile
    // --------------------------------------
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $request->user()->update($request->only([
            'firstname',
            'lastname',
            'bio',
            'facebook_url',
            'avatar',
        ]));

        return $this->successResponse(
            $request->user(),
            'Profile updated successfully',
            200
        );
    }

    // --------------------------------------
    // Change Password
    // --------------------------------------
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        if (!Hash::check($request->current_password, $request->user()->password)) {
            return $this->errorResponse('Current password is incorrect', null, 401);
        }

        $request->user()->update(['password' => $request->new_password]);

        return $this->successResponse(null, 'Password changed successfully', 200);
    }

    // --------------------------------------
    // Helper Methods
    // --------------------------------------
    private function successResponse($data, string $message, int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    private function errorResponse(string $message, $errors = null, int $code = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $code);
    }
}