<?php

namespace App\Models;

use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password', 'person_id', 'home_lodge_id', 'current_lodge_id', 'is_platform_admin',
        'approval_status', 'approved_at', 'approved_by', 'rejection_reason',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
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
            'is_platform_admin' => 'boolean', 'approved_at' => 'datetime', 'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function lodges()
    {
        return $this->belongsToMany(Lodge::class, 'lodge_user_roles')->withPivot('role_id')->withTimestamps();
    }

    public function currentLodge()
    {
        return $this->belongsTo(Lodge::class, 'current_lodge_id');
    }

    public function homeLodge()
    {
        return $this->belongsTo(Lodge::class, 'home_lodge_id');
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function volunteerCommitments()
    {
        return $this->hasMany(EventVolunteerCommitment::class);
    }

    public function createdNewsletterIssues()
    {
        return $this->hasMany(NewsletterIssue::class, 'created_by');
    }

    public function createdLodgeCommunications()
    {
        return $this->hasMany(LodgeCommunication::class, 'created_by');
    }

    public function hasLodgePermission(Lodge $lodge, string $permission): bool
    {
        return $this->is_platform_admin || DB::table('lodge_user_roles')->join('permission_role', 'lodge_user_roles.role_id', '=', 'permission_role.role_id')->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')->where('lodge_user_roles.user_id', $this->id)->where('lodge_user_roles.lodge_id', $lodge->id)->where('permissions.key', $permission)->exists();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new QueuedVerifyEmail);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new QueuedResetPassword($token));
    }
}
