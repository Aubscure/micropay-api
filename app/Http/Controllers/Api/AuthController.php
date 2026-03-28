<?php
// app/Http/Controllers/Api/AuthController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Handles API token authentication via Laravel Sanctum.
 *
 * This is SEPARATE from Fortify's web authentication.
 * Fortify handles browser sessions (cookies).
 * This controller handles mobile/PWA token auth (Bearer tokens).
 */
class AuthController extends Controller
{
    /**
     * Register a new user and return a Sanctum API token.
     *
     * POST /api/auth/register
     */
    public function register(Request $request): JsonResponse
    {
        // Validate incoming data before touching the database
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed', // requires password_confirmation field
        ]);

        // Create the user — password is auto-hashed via the 'hashed' cast on the model
        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => $validated['password'],
        ]);

        // Issue a Sanctum token named 'api' for this device
        // This token is returned ONCE — the client must store it securely
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful.',
            'token'   => $token,
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
        ], 201);
    }

    /**
     * Login and return a Sanctum API token.
     *
     * POST /api/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // Find the user by email
        $user = User::where('email', $request->email)->first();

        // Check password — Hash::check() compares plaintext to hashed value
        if (! $user || ! Hash::check($request->password, $user->password)) {
            // Use a vague error message — don't reveal whether email or password was wrong
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Revoke all previous tokens for this user on this device (optional but cleaner)
        // Comment this out if you want multiple devices logged in simultaneously
        $user->tokens()->delete();

        // Issue a fresh token
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token'   => $token,
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Logout — revoke the current token.
     *
     * POST /api/auth/logout
     * Requires: Authorization: Bearer {token}
     */
    public function logout(Request $request): JsonResponse
    {
        // currentAccessToken() returns the token used in this request
        // delete() revokes it so it can never be used again
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Return the authenticated user's details.
     *
     * GET /api/auth/me
     * Requires: Authorization: Bearer {token}
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => [
                'id'         => $request->user()->id,
                'name'       => $request->user()->name,
                'email'      => $request->user()->email,
                'created_at' => $request->user()->created_at,
            ],
        ]);
    }
}
