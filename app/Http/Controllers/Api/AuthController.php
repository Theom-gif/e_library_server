<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\admin\RegistationAuthor;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Mail\AuthorStatusMail;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    // --------------------------------------
    // Register User
    // --------------------------------------
    public function register(RegisterRequest $request): JsonResponse
    {
        return $this->registerUser($request->validated());
    }

    // --------------------------------------
    // Register Author
    // --------------------------------------
    public function authorRegister(RegistationAuthor $request): JsonResponse
    {
        return $this->registerAuthor($request->validated());
    }

    // --------------------------------------
    // Login
    // --------------------------------------
    public function login(LoginRequest $request): JsonResponse
    {
        $email = $request->email;
        $password = $request->password;

        $user = User::where('email', $email)->first();

        // ✅ FIXED CONDITION
        if ($user && ($user->status !== 'active' || $user->status === 'in_review')) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is not active. Please contact support.',
            ], 403);
        }

        if (!$user || !Hash::check($password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        // delete old tokens
        $user->tokens()->delete();

        // create new token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user,
                'token' => $token,
            ]
        ], 200);
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

    // --------------------------------------
    // Register User Logic
    // --------------------------------------
    private function registerUser(array $validated): JsonResponse
    {
        try {
            $user = User::createUser($validated);

            if (!$user) {
                return $this->errorResponse('Failed to create user', null, 500);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return $this->successResponse([
                'user' => $user,
                'token' => $token,
            ], 'User registered successfully', 201);

        } catch (\Exception $e) {
            return $this->errorResponse('Registration failed', $e->getMessage(), 500);
        }
    }

    // --------------------------------------
    // Register Author Logic
    // --------------------------------------
    private function registerAuthor(array $validated): JsonResponse
    {
        try {
            $user = DB::transaction(function () use ($validated): User {
                $author = User::createAuthor($validated);

                $this->notificationService->notifyAdminsOfAuthorRegistration($author);

                return $author;
            });

            try {
                Mail::to($user->email)->send(
                    new AuthorStatusMail(
                        $user,
                        'Received',
                        'Your author registration has been received and is currently under review.',
                        'https://e-library-portal.app/login',
                        'Click Here To Go In As Author'
                    )
                );
            } catch (\Throwable $mailException) {
                Log::warning('Author registration email failed after request was saved.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $mailException->getMessage(),
                ]);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return $this->successResponse([
                'user' => $user,
                'token' => $token,
            ], 'Author registered successfully', 201);

        } catch (\Throwable $e) {
            return $this->errorResponse('Registration failed', $e->getMessage(), 500);
        }
    }
}
