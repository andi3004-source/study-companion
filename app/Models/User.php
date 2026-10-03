<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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
        'start_date',
        'track',
        'onboarded',
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
            'start_date' => 'date',
            'onboarded' => 'boolean',
        ];
    }

    /** Day number since start_date, 1-indexed. Null until start_date is set. Can be <= 0 if start_date is in the future. */
    public function dayNumber(?\Carbon\CarbonInterface $today = null): ?int
    {
        if (! $this->start_date) {
            return null;
        }

        $today ??= now();

        $start = $this->start_date->copy()->startOfDay()->getTimestamp();
        $now = $today->copy()->startOfDay()->getTimestamp();

        return (int) round(($now - $start) / 86400) + 1;
    }
}
