<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        'password',
        'role',
        'is_active',
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
            'can_book' => 'boolean',
            'is_clinician' => 'boolean',
        ];
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
