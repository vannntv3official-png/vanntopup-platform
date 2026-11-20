<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use App\Models\Role;
use App\Models\User;
use App\Services\Security\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    public function __construct(private readonly AuditService $auditService) {}

    /**
     * Returns Google OAuth 2.0 authorization URL when configured, or signals local dev mode.
     */
    public function redirect(): JsonResponse
    {
        $clientId = (string) config('topup.google_oauth.client_id');
        $clientSecret = (string) config('topup.google_oauth.client_secret');
        $redirectUri = (string) config('topup.google_oauth.redirect_uri');

        if ($clientId === '' || $clientSecret === '') {
            if (app()->environment('production')) {
                return response()->json([
                    'message' => 'Google OAuth is not configured on this server.',
                ], 503);
            }

            return response()->json([
                'mode' => 'dev_simulator',
                'authorization_url' => null,
                'message' => 'Google OAuth credentials not set in .env; local dev simulator is available.',
            ]);
        }

        $state = bin2hex(random_bytes(24));
        Cache::put("google_oauth_state:{$state}", true, now()->addMinutes(10));

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'online',
            'prompt' => 'select_account',
            'state' => $state,
        ]);

        return response()->json([
            'mode' => 'live',
            'authorization_url' => "https://accounts.google.com/o/oauth2/v2/auth?{$query}",
        ]);
    }

    /**
     * Handles real Google OAuth 2.0 callback server-to-server.
     */
    public function callback(Request $request): RedirectResponse
    {
        $storefrontUrl = (string) config('topup.storefront_url', 'http://127.0.0.1:3000');
        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');

        if ($state === '' || $code === '' || ! Cache::pull("google_oauth_state:{$state}")) {
            return redirect()->away("{$storefrontUrl}/account?oauth_error=invalid_state");
        }

        $tokenResponse = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('topup.google_oauth.client_id'),
            'client_secret' => config('topup.google_oauth.client_secret'),
            'redirect_uri' => config('topup.google_oauth.redirect_uri'),
            'grant_type' => 'authorization_code',
        ]);

        if (! $tokenResponse->successful()) {
            return redirect()->away("{$storefrontUrl}/account?oauth_error=token_exchange_failed");
        }

        $accessToken = (string) $tokenResponse->json('access_token', '');
        $profileResponse = Http::withToken($accessToken)->timeout(15)->get('https://openidconnect.googleapis.com/v1/userinfo');

        if (! $profileResponse->successful()) {
            return redirect()->away("{$storefrontUrl}/account?oauth_error=userinfo_failed");
        }

        $googleSub = (string) $profileResponse->json('sub', '');
        $email = strtolower((string) $profileResponse->json('email', ''));
        $emailVerified = (bool) $profileResponse->json('email_verified', false);
        $name = (string) ($profileResponse->json('name') ?: 'VannTopUp Gamer');
        $avatar = $profileResponse->json('picture');

        if ($googleSub === '' || $email === '' || ! $emailVerified) {
            return redirect()->away("{$storefrontUrl}/account?oauth_error=unverified_google_email");
        }

        $user = $this->findOrCreateGoogleUser($googleSub, $email, $name, is_string($avatar) ? $avatar : null);
        $token = $user->createToken('google-oauth')->plainTextToken;

        $this->auditService->log('auth.google_login', 'user', $user->id, $user->id, 'customer', null, ['provider' => 'google'], null, $request);

        return redirect()->away("{$storefrontUrl}/account?oauth_token=".urlencode($token));
    }

    /**
     * DEVELOPMENT / TESTING ONLY: Deterministic Google Sign-In simulator when GOOGLE_CLIENT_ID is not set.
     * Strictly forbidden in production.
     */
    public function devLogin(Request $request): JsonResponse
    {
        if (app()->environment('production')) {
            return response()->json(['message' => 'Google dev simulator is disabled in production.'], 403);
        }

        $validated = $request->validate([
            'email' => ['nullable', 'email', 'max:180'],
            'name' => ['nullable', 'string', 'min:2', 'max:120'],
        ]);

        $email = strtolower((string) ($validated['email'] ?? 'vann.gamer@gmail.com'));
        $name = (string) ($validated['name'] ?? 'Vann Gamer (Google)');
        $googleSub = 'dev-google-'.substr(hash('sha256', $email), 0, 16);

        $user = $this->findOrCreateGoogleUser($googleSub, $email, $name, null);
        $token = $user->createToken('google-dev-oauth')->plainTextToken;

        $this->auditService->log('auth.google_dev_login', 'user', $user->id, $user->id, 'customer', null, ['provider' => 'google_dev'], null, $request);

        return response()->json([
            'token' => $token,
            'mode' => 'dev_simulator',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar_url' => $user->avatar_url,
                'auth_provider' => $user->auth_provider,
                'preferred_locale' => $user->preferred_locale,
                'preferred_currency' => $user->preferred_currency,
                'roles' => $user->roles()->pluck('slug')->all(),
                'permissions' => $user->permissionSlugs(),
                'mfa_enabled' => $user->mfa_enabled,
            ],
        ]);
    }

    private function findOrCreateGoogleUser(string $googleSub, string $email, string $name, ?string $avatarUrl): User
    {
        $user = User::query()
            ->where('google_id', $googleSub)
            ->orWhere('email', $email)
            ->first();

        if (! $user) {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'google_id' => $googleSub,
                'avatar_url' => $avatarUrl,
                'password' => Hash::make(Str::random(40)),
                'preferred_locale' => 'km',
                'preferred_currency' => 'USD',
                'status' => 'active',
                'auth_provider' => 'google',
                'email_verified_at' => now(),
                'last_login_at' => now(),
            ]);

            $customerRole = Role::query()->where('slug', 'customer')->first();
            if ($customerRole) {
                $user->roles()->syncWithoutDetaching([$customerRole->id]);
            }
            CustomerProfile::query()->firstOrCreate(['user_id' => $user->id]);
        } else {
            $user->google_id = $user->google_id ?: $googleSub;
            if ($avatarUrl) {
                $user->avatar_url = $avatarUrl;
            }
            $user->last_login_at = now();
            $user->save();
        }

        return $user;
    }
}
