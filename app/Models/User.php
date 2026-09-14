<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\Microsoft\CalendarOwner;
use App\Services\Microsoft\SharedMailbox;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /** Vinculación con Microsoft 365 (tokens de Graph). */
    public function microsoftAccount(): HasOne
    {
        return $this->hasOne(MicrosoftAccount::class);
    }

    /**
     * Calendario sobre el que trabaja este usuario. En modo aplicación todos
     * comparten el buzón general; en delegado, cada quien usa su cuenta.
     */
    public function calendarOwner(): ?CalendarOwner
    {
        return config('services.microsoft.mode') === 'application'
            ? SharedMailbox::configured()
            : $this->microsoftAccount;
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
        ];
    }
}
