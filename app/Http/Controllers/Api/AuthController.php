<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\admin\AdminLoginRequest;
use App\Http\Requests\admin\RegistationAuthor;
use App\Http\Requests\Admin\Category\BookViewRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Register a new user.
     *
     * @param RegisterRequest $request
     * @return JsonResponse
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            // Create new user
            $user = User::create([
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role_id' => $request->role_id,
            ]);

            // Generate authentication token
            $token = $user->createToken('auth_token')->plainTextToken;

            return $this->successResponse([
                'user' => $user,
                'token' => $token,
            ], 'User registered successfully', 201);

        } catch (\Exception $e) {
            return $this->errorResponse('Registration failed', $e->getMessage(), 500);
        }
    }
    /**
     * Register a new author.
     *
     * @param RegistationAuthor $request
     * @return JsonResponse
     */
    public function authorRegister(RegistationAuthor $request): JsonResponse
    {
        try {
        
            // Create new user
            $user = User::create([
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role_id' => $request->role_id,
            ]);

            // Generate authentication token
            $token = $user->createToken('auth_token')->plainTextToken;

            return $this->successResponse([
                'user' => $user,
                'token' => $token,
            ], 'User registered successfully', 201);

        } catch (\Exception $e) {
            return $this->errorResponse('Registration failed', $e->getMessage(), 500);
        }
    }

    /**
     * Login user.
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            // Find user by email
            $user = User::where('email', $request->email)->first();

            // Check if user exists and password is correct
            if (!$user || !Hash::check($request->password, $user->password)) {
                return $this->errorResponse('Invalid credentials', 'Email or password is incorrect', 401);
            }

            // Generate authentication token
            $token = $user->createToken('auth_token')->plainTextToken;

            return $this->successResponse([
                'user' => $user,
                'token' => $token,
            ], 'Login successful', 200);

        } catch (\Exception $e) {
            return $this->errorResponse('Login failed', $e->getMessage(), 500);
        }
    }
    /**
     * Login user.
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function adminLogin(AdminLoginRequest $request): JsonResponse
    {
        try {
            // Find user by email
            $user = User::where('email', $request->email)->first();

            // Check if user exists and password is correct
            if (!$user || !Hash::check($request->password, $user->password)) {
                return $this->errorResponse('Invalid credentials', 'Email or password is incorrect', 401);
            }

            // Generate authentication token
            $token = $user->createToken('auth_token')->plainTextToken;

            return $this->successResponse([
                'user' => $user,
                'token' => $token,
            ], 'Login successful', 200);

        } catch (\Exception $e) {
            return $this->errorResponse('Login failed', $e->getMessage(), 500);
        }
    }

    /**
     * Logout user (revoke token).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            // Revoke current token
            $request->user()->currentAccessToken()->delete();

            return $this->successResponse(null, 'Logout successful', 200);

        } catch (\Exception $e) {
            return $this->errorResponse('Logout failed', $e->getMessage(), 500);
        }
    }

    /**
     * Request password reset (send email with reset link).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function requestPasswordReset(Request $request): JsonResponse
    {
        try {
            // Validate incoming request
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', $validator->errors(), 422);
            }

            // Find user
            $user = User::where('email', $request->email)->first();

            // Generate password reset token
            $token = Str::random(64);
            
            // Store reset token in database
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'email' => $user->email,
                    'token' => Hash::make($token),
                    'created_at' => now(),
                ]
            );

            // TODO: Send reset email with token
            // Mail::send('emails.password-reset', ['token' => $token], function($message) use ($user) {
            //     $message->to($user->email);
            // });

            return $this->successResponse(null, 'Password reset link sent to your email', 200);

        } catch (\Exception $e) {
            return $this->errorResponse('Request failed', $e->getMessage(), 500);
        }
    }

    /**
     * Reset password with token.
     *
     * @param ResetPasswordRequest $request
     * @return JsonResponse
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            // Check if reset token exists
            $resetRecord = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->first();

            if (!$resetRecord) {
                return $this->errorResponse('Invalid token', 'No reset request found for this email', 400);
            }

            // Verify token
            if (!Hash::check($request->token, $resetRecord->token)) {
                return $this->errorResponse('Invalid token', 'The reset token is invalid or expired', 400);
            }

            // Check if token is not expired (1 hour)
            if (now()->diffInHours($resetRecord->created_at) > 1) {
                return $this->errorResponse('Expired token', 'The reset token has expired', 400);
            }

            // Update user password
            $user = User::where('email', $request->email)->first();
            $user->update(['password' => Hash::make($request->password)]);

            // Delete reset token
            DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->delete();

            return $this->successResponse(null, 'Password reset successfully', 200);

        } catch (\Exception $e) {
            return $this->errorResponse('Reset failed', $e->getMessage(), 500);
        }
    }

    /**
     * Get current authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getCurrentUser(Request $request): JsonResponse
    {
        try {
            return $this->successResponse($request->user(), 'User retrieved successfully', 200);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve user', $e->getMessage(), 500);
        }
    }

    /**
     * Update user profile.
     *
     * @param UpdateProfileRequest $request
     * @return JsonResponse
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        try {
            // Update user
            $request->user()->update($request->only([
                'firstname',
                'lastname',
                'bio',
                'facebook_url',
                'avatar',
            ]));

            return $this->successResponse($request->user(), 'Profile updated successfully', 200);

        } catch (\Exception $e) {
            return $this->errorResponse('Update failed', $e->getMessage(), 500);
        }
    }

    /**
     * Change password.
     *
     * @param ChangePasswordRequest $request
     * @return JsonResponse
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            // Verify current password
            if (!Hash::check($request->current_password, $request->user()->password)) {
                return $this->errorResponse('Invalid password', 'Current password is incorrect', 401);
            }

            // Update password
            $request->user()->update(['password' => Hash::make($request->new_password)]);

            return $this->successResponse(null, 'Password changed successfully', 200);

        } catch (\Exception $e) {
            return $this->errorResponse('Change password failed', $e->getMessage(), 500);
        }
    }

    /**
     * Store a book view record.
     *
     * @param BookViewRequest $request
     * @return JsonResponse
     */
    public function storeBookView(BookViewRequest $request): JsonResponse
    {
        try {
            $now = now();
            $bookViewId = DB::table('book_views')->insertGetId([
                'book_id' => $request->book_id,
                'user_id' => $request->user_id ?? optional($request->user())->id,
                'ip_address' => $request->ip_address ?? $request->ip(),
                'user_agent' => $request->user_agent ?? $request->userAgent(),
                'viewed_at' => $request->viewed_at ?? $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $bookView = DB::table('book_views')->where('id', $bookViewId)->first();

            return $this->successResponse($bookView, 'Book view saved successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to save book view', $e->getMessage(), 500);
        }
    }

    /**
     * Success response helper.
     *
     * @param mixed $data
     * @param string $message
     * @param int $code
     * @return JsonResponse
     */
    private function successResponse($data, string $message, int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Error response helper.
     *
     * @param string $message
     * @param mixed $errors
     * @param int $code
     * @return JsonResponse
     */
    private function errorResponse(string $message, $errors = null, int $code = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }
}
