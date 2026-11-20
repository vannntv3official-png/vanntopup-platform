<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdminSession;
use App\Models\CustomerProfile;
use App\Models\Role;
use App\Models\User;
use App\Services\Security\AuditService;
use App\Services\Security\MfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function __construct(
        private readonly MfaService $mfaService,
        private readonly AuditService $auditService,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'min:8', 'max:24'],
            'password' => ['required', 'confirmed', Password::min(10)],
            'preferred_locale' => ['nullable', 'in:km,en'],
            'preferred_currency' => ['nullable', 'in:USD,KHR'],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'preferred_locale' => $validated['preferred_locale'] ?? 'km',
            'preferred_currency' => $validated['preferred_currency'] ?? 'USD',
            'status' => 'active',
        ]);

        $customerRole = Role::query()->where('slug', 'customer')->first();
        if ($customerRole) {
            $user->roles()->attach($customerRole->id);
        }

        CustomerProfile::query()->create(['user_id' => $user->id]);

        $token = $user->createToken('storefront-auth')->plainTextToken;

        $this->auditService->log('user.registered', 'user', $user->id, $user->id, 'customer', null, ['email' => $user->email], null, $request);

        return response()->json([
            'token' => $token,
            'user' => $this->serializeUser($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'mfa_code' => ['nullable', 'string', 'size:6'],
        ]);

        /** @var User|null $user */
        $user = User::query()->where('email', strtolower($validated['email']))->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            $this->auditService->log('auth.login_failed', 'user', $user?->id, null, 'guest', null, ['email' => $validated['email']], null, $request);

            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        if ($user->status !== 'active') {
            return response()->json(['message' => 'Account is suspended or inactive.'], 403);
        }

        $mfaVerified = false;
        if ($user->mfa_enabled) {
            $code = (string) ($validated['mfa_code'] ?? '');
            if ($code === '') {
                return response()->json([
                    'message' => 'MFA verification code required.',
                    'mfa_required' => true,
                ], 422);
            }

            if (! $user->mfa_secret_encrypted || ! $this->mfaService->verifyCode($user->mfa_secret_encrypted, $code)) {
                return response()->json(['message' => 'Invalid MFA verification code.'], 401);
            }
            $mfaVerified = true;
        }

        $user->last_login_at = now();
        $user->save();

        $newToken = $user->createToken($user->isAdmin() ? 'admin-session' : 'customer-session');

        if ($user->isAdmin()) {
            AdminSession::query()->create([
                'user_id' => $user->id,
                'token_id' => (string) $newToken->accessToken->id,
                'ip_address' => (string) $request->ip(),
                'user_agent' => $request->userAgent(),
                'mfa_verified' => $mfaVerified,
                'last_active_at' => now(),
            ]);
        }

        $this->auditService->log('auth.login_succeeded', 'user', $user->id, $user->id, $user->isAdmin() ? 'admin' : 'customer', null, ['mfa_verified' => $mfaVerified], null, $request);

        return response()->json([
            'token' => $newToken->plainTextToken,
            'user' => $this->serializeUser($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->serializeUser($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        if ($token) {
            AdminSession::query()
                ->where('user_id', $user->id)
                ->where('token_id', (string) $token->id)
                ->update(['revoked_at' => now()]);
            $token->delete();
        }

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'min:8', 'max:24'],
            'preferred_locale' => ['sometimes', 'in:km,en'],
            'preferred_currency' => ['sometimes', 'in:USD,KHR'],
            'notify_email' => ['sometimes', 'boolean'],
            'notify_in_app' => ['sometimes', 'boolean'],
            'notify_telegram' => ['sometimes', 'boolean'],
        ]);

        $user->fill(collect($validated)->only(['name', 'phone', 'preferred_locale', 'preferred_currency'])->all());
        $user->save();

        if ($user->customerProfile) {
            $user->customerProfile->fill(collect($validated)->only(['notify_email', 'notify_in_app', 'notify_telegram'])->all());
            $user->customerProfile->save();
        }

        return response()->json(['user' => $this->serializeUser($user->fresh())]);
    }

    public function setupMfa(Request $request): JsonResponse
    {
        $user = $request->user();
        $secret = $this->mfaService->generateSecret();
        $user->mfa_secret_encrypted = $secret;
        $user->save();

        $otpauthUri = sprintf(
            'otpauth://totp/VannTopUp:%s?secret=%s&issuer=VannTopUp',
            rawurlencode($user->email),
            $secret,
        );

        return response()->json([
            'secret' => $secret,
            'otpauth_uri' => $otpauthUri,
        ]);
    }

    public function confirmMfa(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'size:6']]);
        $user = $request->user();

        if (! $user->mfa_secret_encrypted || ! $this->mfaService->verifyCode($user->mfa_secret_encrypted, $validated['code'])) {
            return response()->json(['message' => 'Invalid MFA verification code.'], 422);
        }

        $user->mfa_enabled = true;
        $user->save();

        $this->auditService->log('auth.mfa_enabled', 'user', $user->id, $user->id, $user->isAdmin() ? 'admin' : 'customer', null, null, null, $request);

        return response()->json(['message' => 'MFA enabled successfully.', 'user' => $this->serializeUser($user)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_url,
            'auth_provider' => $user->auth_provider ?? 'email',
            'preferred_locale' => $user->preferred_locale,
            'preferred_currency' => $user->preferred_currency,
            'roles' => $user->roles()->pluck('slug')->all(),
            'permissions' => $user->permissionSlugs(),
            'mfa_enabled' => $user->mfa_enabled,
        ];
    }
}
