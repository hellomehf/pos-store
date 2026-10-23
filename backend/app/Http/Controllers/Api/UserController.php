<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::select('id', 'name', 'email', 'role', 'created_at')
            ->withCount('sales')
            ->orderBy('name')
            ->get();

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)],
            'role' => 'required|in:admin,cashier,stocker',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        return response()->json($user->only('id', 'name', 'email', 'role', 'created_at'), 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user->only('id', 'name', 'email', 'role', 'created_at'));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required|in:admin,cashier,stocker',
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        } else {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json($user->only('id', 'name', 'email', 'role', 'created_at'));
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->id === auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Cannot delete your own account'], 422);
        }

        $salesCount = $user->sales()->count();

        if ($salesCount > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete user account. This user is linked to existing sales transactions. Please deactivate the account instead.',
            ], 422);
        }

        try {
            $deleted = $user->delete();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("User delete failed: " . $e->getMessage());
            return response()->json(['message' => 'Delete failed: ' . $e->getMessage()], 500);
        }

        if (!$deleted) {
            return response()->json(['message' => 'Delete returned false — user may not exist.'], 404);
        }

        return response()->json(['message' => 'User deleted successfully']);
    }
}
