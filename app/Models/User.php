<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use NotificationChannels\WebPush\HasPushSubscriptions;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property string|null $device_token
 * @property string|null $fill_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'device_token', 'fill_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (User $user): void {
            $vault = Vault::create([
                'name' => "{$user->name}'s Vault",
                'type' => Vault::TYPE_PERSONAL,
                'created_by' => $user->id,
            ]);

            $user->vaults()->attach($vault);
        });
    }

    /**
     * @return BelongsToMany<Vault, $this>
     */
    public function vaults(): BelongsToMany
    {
        return $this->belongsToMany(Vault::class)->withTimestamps();
    }

    public function personalVault(): ?Vault
    {
        return $this->vaults()
            ->where('type', Vault::TYPE_PERSONAL)
            ->where('created_by', $this->id)
            ->first();
    }

    // ---- Phone keys (Settings → Phone; also `php artisan vault:token`) ----
    // Two independent keys for /api/lookup: the device key the Shortcut sends
    // as a header, and the fill key the in-page filler embeds (Origin-scoped,
    // so it can be rotated on its own if a page leaks it).

    /** @var list<string> */
    public const PHONE_TOKENS = ['device_token', 'fill_token'];

    /** Mint or roll one of the phone keys; the previous value stops working at once. */
    public function regeneratePhoneToken(string $column): string
    {
        $this->assertPhoneTokenColumn($column);
        $this->forceFill([$column => Str::random(48)])->save();

        return $this->{$column};
    }

    public function revokePhoneToken(string $column): void
    {
        $this->assertPhoneTokenColumn($column);
        $this->forceFill([$column => null])->save();
    }

    private function assertPhoneTokenColumn(string $column): void
    {
        if (! in_array($column, self::PHONE_TOKENS, true)) {
            throw new \InvalidArgumentException("Unknown phone token column: {$column}");
        }
    }
}
