<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function accessRevision(): int
    {
        return (int) AdminAuditEvent::query()->where('subject_id', $this->id)->whereIn('action', ['user.deactivated', 'user.password_changed'])->max('id');
    }

    public function editVersion(): string
    {
        return hash('sha256', serialize([$this->id, $this->name, $this->email, $this->username, $this->role ?? 'staff', (bool) ($this->is_active ?? true), $this->password, $this->updated_at?->format('Y-m-d H:i:s')]));
    }

    public function canAccessAdmin(): bool
    {
        return ($this->is_active ?? true) && in_array($this->role ?? 'staff', ['staff', 'super_admin'], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->canAccessAdmin() && $this->role === 'super_admin';
    }

    public function eventProjects(): HasMany
    {
        return $this->hasMany(EventProject::class, 'owner_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
