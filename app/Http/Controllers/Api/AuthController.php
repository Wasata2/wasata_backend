<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
            'password'  => ['required', 'string', 'min:8'],
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

    // PUT /api/auth/profile  (requires auth:sanctum middleware)
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'full_name'        => ['sometimes', 'string', 'max:150'],
            'phone'            => ['sometimes', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
            'profile_picture'  => ['sometimes', 'nullable', 'image', 'max:4096'], // 4MB max, same limit as Store::image
            // Same Gaza-only city list used in StoreController — keep the two in sync
            // if the list ever changes.
            'location'         => ['sometimes', 'nullable', 'string', 'in:غزة,شمال غزة,الوسطى,خانيونس,رفح'],
        ]);

        // Files arrive separately from validate()'s return value — same pattern as
        // StoreController::update() and OrderController::store().
        if ($request->hasFile('profile_picture')) {
            $validated['profile_picture'] = $request->file('profile_picture')->store('profile-pictures', 'public');
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user'    => $user->load('role'),
        ], 200);
    }

    // POST /api/auth/forgot-password
    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        // Always return the same message whether the email exists or not —
        // this stops someone from using this endpoint to find out who is registered.
        $genericResponse = response()->json([
            'message' => 'If that email is registered, a reset link has been generated.',
        ], 200);

        if (! $user) {
            return $genericResponse;
        }

        $token = Str::random(64);

        // Replace any previous token for this email with a fresh one
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        DB::table('password_reset_tokens')->insert([
            'email'      => $user->email,
            'token'      => Hash::make($token),
            'created_at' => now(),
        ]);

        // TEMPORARY (dev only): since MAIL_MAILER=log, no real email is sent.
        // We return the raw token directly so the frontend can proceed without email.
        // Before going live, replace this with an actual emailed reset link and
        // remove 'reset_token' from the response below.
        return response()->json([
            'message'     => 'If that email is registered, a reset link has been generated.',
            'reset_token' => $token, // ⚠️ remove this line once real email sending is set up
        ], 200);
    }

    // POST /api/auth/reset-password
    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email'    => ['required', 'email'],
            'token'    => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $validated['email'])->first();

        if (! $record || ! Hash::check($validated['token'], $record->token)) {
            return response()->json([
                'message' => 'This reset link is invalid.',
            ], 400);
        }

        // Token expires after 60 minutes
        if (now()->diffInMinutes($record->created_at) > 60) {
            return response()->json([
                'message' => 'This reset link has expired. Please request a new one.',
            ], 400);
        }

        $user = User::where('email', $validated['email'])->firstOrFail();
        $user->update(['password' => $validated['password']]); // auto-hashed by the model's cast

        // Token is single-use — remove it once it's been used
        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        // Log the user out of all existing sessions/devices for security
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password reset successfully. Please log in with your new password.',
        ], 200);
    }
}
