<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'role',
        'email',
        'phone',
        'is_active',
        'last_login_at',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'role'              => UserRole::class,
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        // Super-admin has no tenant — always allowed
        if (is_null($this->tenant_id)) {
            return true;
        }

        return $this->tenant?->isAccessible() ?? false;
    }

    public function isSuperAdmin(): bool
    {
        return is_null($this->tenant_id);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function hasRole(UserRole $role): bool
    {
        return $this->role === $role;
    }

    public function canManageVendors(): bool
    {
        return $this->role->canManageVendors();
    }

    public function canManageInvoices(): bool
    {
        return $this->role->canManageInvoices();
    }

    public function canManageUsers(): bool
    {
        return $this->role->canManageUsers();
    }
}
