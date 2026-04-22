<?php
// app/Http/Controllers/Api/AuthController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Handles API authentication via Laravel session cookies.
 * Modified for stateful SPA authentication and offline queue cryptography.
 */
class AuthController extends Controller
{
    /**
     * Register a new user and initialize a secure cookie session.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => $validated['password'],
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        // SECURITY: Generate a secure nonce tied strictly to this active session.
        $nonce = bin2hex(random_bytes(32));
        $request->session()->put('offline_encryption_nonce', $nonce);

        // Fallback: Issue a Sanctum token for cross-domain clients where session cookies
        // may not persist reliably (multi-instance free tiers, strict browsers, etc).
        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful.',
            'token'   => $token,
            'user'    => [
                'id'                       => $user->id,
                'name'                     => $user->name,
                'email'                    => $user->email,
                'offline_encryption_nonce' => $nonce,
            ],
        ], 201);
    }

    /**
     * Authenticate user and initialize a secure cookie session.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            // SECURITY: Generate a fresh nonce on every new login.
            $nonce = bin2hex(random_bytes(32));
            $request->session()->put('offline_encryption_nonce', $nonce);

            /** @var \App\Models\User $user */
            $user = Auth::user();

            // Fallback: issue token so auth survives even if session is not sticky.
            $token = $user->createToken('spa')->plainTextToken;

            return response()->json([
                'message' => 'Login successful.',
                'token'   => $token,
                'user'    => [
                    'id'                       => $user->id,
                    'name'                     => $user->name,
                    'email'                    => $user->email,
                    'offline_encryption_nonce' => $nonce,
                ],
            ]);
        }

        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }

    /**
     * Invalidate the session and clear cookies.
     */
    public function logout(Request $request): JsonResponse
    {
        // If a Sanctum token was used, revoke it.
        $request->user()?->currentAccessToken()?->delete();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Return the authenticated user's details and the session nonce.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => [
                'id'                       => $request->user()->id,
                'name'                     => $request->user()->name,
                'email'                    => $request->user()->email,
                'created_at'               => $request->user()->created_at,
                // Pass the nonce to the frontend to be held strictly in active memory
                'offline_encryption_nonce' => $request->session()->get('offline_encryption_nonce'),
            ],
        ]);
    }
}