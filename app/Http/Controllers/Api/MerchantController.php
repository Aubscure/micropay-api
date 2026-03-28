<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages merchant profiles for authenticated users.
 * One user can have one merchant profile.
 */
class MerchantController extends Controller
{
    /**
     * GET /api/merchants
     * List all merchants belonging to the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        // Only return merchants owned by the logged-in user
        $merchants = Merchant::where('user_id', $request->user()->id)->get();

        return response()->json(['data' => $merchants]);
    }

    /**
     * POST /api/merchants
     * Create a merchant profile for the authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'business_type' => 'nullable|string|max:100',
        ]);

        // Prevent creating duplicate merchant profiles
        $existing = Merchant::where('user_id', $request->user()->id)->first();

        if ($existing) {
            return response()->json([
                'message' => 'You already have a merchant profile.',
                'data'    => $existing,
            ], 409); // 409 Conflict
        }

        $merchant = Merchant::create([
            'user_id'       => $request->user()->id,
            'business_name' => $validated['business_name'],
            'business_type' => $validated['business_type'] ?? null,
        ]);

        return response()->json([
            'message' => 'Merchant profile created.',
            'data'    => $merchant,
        ], 201);
    }

    /**
     * GET /api/merchants/{merchant}
     */
    public function show(Request $request, Merchant $merchant): JsonResponse
    {
        // Ensure the authenticated user owns this merchant profile
        if ($merchant->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json(['data' => $merchant]);
    }

    /**
     * PATCH /api/merchants/{merchant}
     */
    public function update(Request $request, Merchant $merchant): JsonResponse
    {
        if ($merchant->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'business_name' => 'sometimes|string|max:255',
            'business_type' => 'nullable|string|max:100',
        ]);

        $merchant->update($validated);

        return response()->json(['message' => 'Merchant updated.', 'data' => $merchant]);
    }

    /**
     * DELETE /api/merchants/{merchant}
     */
    public function destroy(Request $request, Merchant $merchant): JsonResponse
    {
        if ($merchant->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $merchant->delete(); // Soft delete — data is preserved

        return response()->json(['message' => 'Merchant removed.'], 200);
    }
}