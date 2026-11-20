<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'google_id',
        'phone',
        'avatar_url',
        'password',
        'preferred_locale',
        'preferred_currency',
        'status',
        'auth_provider',
        'mfa_enabled',
        'mfa_secret_encrypted',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'mfa_enabled' => 'boolean',
            'mfa_secret_encrypted' => 'encrypted',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function customerProfile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function adminSessions(): HasMany
    {
        return $this->hasMany(AdminSession::class);
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles()->where('slug', $slug)->exists();
    }

    public function isAdmin(): bool
    {
        return $this->roles()->whereIn('slug', ['super_admin', 'operations_admin', 'finance_admin', 'support_agent'])->exists();
    }

    /**
     * @return list<string>
     */
    public function permissionSlugs(): array
    {
        return $this->roles()
            ->with('permissions:id,slug')
            ->get()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('slug'))
            ->unique()
            ->values()
            ->all();
    }

    public function hasPermission(string $permissionSlug): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        return in_array($permissionSlug, $this->permissionSlugs(), true);
    }
}
