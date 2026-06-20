<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name', 'surname', 'email', 'password', 'nickname', 'role', 'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_admin'          => 'boolean',
    ];

    public function belongsToFamily(int $familyId): bool
    {
        if ($this->ownedFamilies()->where('id', $familyId)->exists()) {
            return true;
        }

        return $this->families()
            ->where('families.id', $familyId)
            ->wherePivot('status', 'accepted')
            ->exists();
    }

    public function ownedFamilies()
    {
        return $this->hasMany(Family::class, 'owner_id');
    }
    public function families()  // quelle di cui è membro
    {
        return $this->belongsToMany(Family::class, 'family_user')
                    ->withPivot('status')
                    ->withTimestamps();
    }

    public function financialBalances()
{
    return $this->hasMany(FinancialBalance::class);
}


public function expenses()
{
    return $this->hasMany(Expense::class);
}

public function incomes()
{
    return $this->hasMany(Income::class);
}

public function walletMovements()
{
    return $this->hasMany(WalletMovement::class);
}

}
