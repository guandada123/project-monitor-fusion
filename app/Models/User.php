<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property-read Collection<int,Task> $pending_tasks
 * @property-read Collection<int,Task> $overdue_tasks
 * @property-read DatabaseNotificationCollection $unreadNotifications
 * @property-read float $workload_percent
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    /** @use HasFactory<UserFactory> */

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
        ];
    }

    // Relationships
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function ownedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    // Computed
    public function getWorkloadPercentAttribute(): float
    {
        $total = $this->tasks()
            ->whereNotIn('status', ['done'])
            ->sum('estimate');

        return min(round($total / 40 * 100, 1), 200); // cap at 200%
    }

    // ──── Role & Permission ────

    /**
     * Check if user has a specific role (by slug).
     */
    public function hasRole(string $slug): bool
    {
        return Cache::remember("user:{$this->id}:roles", 300, fn () => $this->roles->pluck('slug')
        )->contains($slug);
    }

    /**
     * Check if user has a specific permission (by slug).
     */
    public function hasPermission(string $slug): bool
    {
        return Cache::remember("user:{$this->id}:permissions", 300, function () {
            return $this->roles()
                ->with('permissions')
                ->get()
                ->pluck('permissions')
                ->flatten()
                ->pluck('slug')
                ->unique();
        })->contains($slug);
    }

    /**
     * Check if user has any of the given permissions.
     */
    public function hasAnyPermission(array $slugs): bool
    {
        foreach ($slugs as $slug) {
            if ($this->hasPermission($slug)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all of the given permissions.
     */
    public function hasAllPermissions(array $slugs): bool
    {
        foreach ($slugs as $slug) {
            if (! $this->hasPermission($slug)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Assign a role by slug.
     */
    public function assignRole(string $slug): void
    {
        $role = Role::where('slug', $slug)->first();
        if ($role && ! $this->hasRole($slug)) {
            $this->roles()->attach($role);
            $this->clearPermissionCache();
        }
    }

    /**
     * Clear the permission cache for this user.
     */
    public function clearPermissionCache(): void
    {
        Cache::forget("user:{$this->id}:roles");
        Cache::forget("user:{$this->id}:permissions");
    }

    /**
     * Determine whether this user can access a specific project.
     *
     * Rules:
     *  - Admins (role: admin) can access ALL projects.
     *  - Users with manage_projects permission can access ALL projects.
     *  - Project owners can access their own projects.
     *  - All other users: access denied.
     */
    public function canAccessProject(Project $project): bool
    {
        // Admin role — full access
        if ($this->hasRole('admin')) {
            return true;
        }

        // System-level project management — full access
        if ($this->hasPermission('manage_projects')) {
            return true;
        }

        // Project owner — access only their own projects
        return $this->id === $project->owner_id;
    }

    /**
     * Get the IDs of all projects this user can access (for query scoping).
     */
    public function accessibleProjectIds(): array
    {
        if ($this->hasRole('admin') || $this->hasPermission('manage_projects')) {
            return Project::pluck('id')->all();
        }

        return $this->ownedProjects()->pluck('id')->all();
    }
}
