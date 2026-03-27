<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\admin\RegistationAuthor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    // --------------------------------------
    // Register User
    // --------------------------------------
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            return $this->registerUser($request->validated());
        } catch (\Exception $e) {
            return $this->errorResponse('Registration failed', $e->getMessage(), 500);
        }
    }

    // --------------------------------------
    // Register Author
    // --------------------------------------
    public function authorRegister(RegistationAuthor $request): JsonResponse
    {
        try {
            return $this->registerUser($request->validated());
        } catch (\Exception $e) {
            return $this->errorResponse('Registration failed', $e->getMessage(), 500);
        }
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

        $user->tokens()->delete();
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

    /**
     * @param array<string, mixed> $validated
     */
    private function registerUser(array $validated): JsonResponse
    {
        $user = User::create([
            'firstname' => $validated['firstname'],
            'lastname' => $validated['lastname'],
            'email' => $validated['email'],
            'password' => $validated['password'], // auto-hashed by model
            'role_id' => $validated['role_id'],
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'user' => $user,
            'token' => $token,
        ], 'User registered successfully', 201);
    }

}
