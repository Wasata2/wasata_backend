<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    // POST /api/auth/register
    public function register(Request $request)
    {
        // Scenario 2: Missing required fields -> handled by 'required' rules below
        // Scenario 3: Duplicate email/phone -> handled by 'unique' rules below
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'email'     => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone'     => ['required', 'string', 'max:20', 'unique:users,phone'],
            'password'  => ['required', 'string', 'min:8', 'confirmed'],
            'role'      => ['required', Rule::in(['customer', 'broker'])], // admins are not self-registered
        ], [
            'email.unique' => 'Email or phone number already registered.',
            'phone.unique' => 'Email or phone number already registered.',
        ]);

        $role = Role::where('role_name', $validated['role'])->firstOrFail();

        $user = User::create([
            'full_name'      => $validated['full_name'],
            'email'          => $validated['email'],
            'phone'          => $validated['phone'],
            'password'       => $validated['password'], // auto-hashed by the model's 'hashed' cast
            'role_id'        => $role->id,
            'account_status' => 'active', // active immediately, per acceptance criteria
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful.',
            'user'    => $user->load('role'),
            'token'   => $token,
        ], 201);
    }

    // POST /api/auth/login
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        // Check both: user exists AND password matches the hash
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password.',
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user'    => $user->load('role'),
            'token'   => $token,
        ], 200);
    }

    // POST /api/auth/logout  (requires auth:sanctum middleware)
    public function logout(Request $request)
    {
        // Deletes only the token used in this request (logs out this device/session)
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ], 200);
    }

    // GET /api/auth/me  (requires auth:sanctum middleware)
    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()->load('role'),
        ], 200);
    }
}
