<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'position',
        'password',
        'role',
        'is_active',
        'login_enabled',
        'can_book',
        'is_clinician',
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
            'role' => Role::class,
            'is_active' => 'boolean',
            'login_enabled' => 'boolean',
            'can_book' => 'boolean',
            'is_clinician' => 'boolean',
        ];
    }

    /**
     * Emails are stored trimmed and lowercased so the unique index, the login
     * lookup and duplicate detection in the roster import all agree. Roster-only
     * staff may have none at all, so blank passes through as null.
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null || trim($value) === ''
                ? null
                : mb_strtolower(trim($value)),
        );
    }

    public function deviceTokens(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function events(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_staff');
    }

    public function gymSchedules(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(GymSchedule::class, 'gym_schedule_staff');
    }

    public function clinicAppointments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ClinicAppointment::class, 'assigned_staff_id');
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(StaffLeave::class);
    }

    /**
     * Who may receive a notification: active staff who can actually sign in.
     *
     * Roster-only staff are excluded deliberately - they have no way to read
     * anything, and queueing it up would greet them with a stale backlog on
     * the day they're invited. Every command and broadcast goes through this.
     */
    public function scopeNotifiable(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('login_enabled', true);
    }

    /** The in-memory counterpart of scopeNotifiable(), for loaded relations. */
    public function isNotifiable(): bool
    {
        return $this->is_active && $this->login_enabled;
    }

    /** Roster staff tracked in the app who have no way into it. */
    public function isRosterOnly(): bool
    {
        return ! $this->login_enabled;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [Role::Admin, Role::SuperAdmin], true);
    }

    /**
     * May open the full SM Clinic module: manage bookings and the client
     * database. Admins get this by virtue of their role.
     */
    public function canUseClinic(): bool
    {
        return $this->isAdmin() || $this->can_book;
    }

    /**
     * May be assigned appointments, and so gets the scoped "My Appointments"
     * view. Also true for anyone who already holds assignments, so revoking
     * the flag never strands existing bookings.
     */
    public function hasClinicAssignments(): bool
    {
        return $this->is_clinician || $this->clinicAppointments()->exists();
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SuperAdmin;
    }
}
