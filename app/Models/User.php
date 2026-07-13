<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'fname',
        'lname',
        'email',
        'permissions',
        'gdpr_subscriped_emails',
        'deleted',
        'init',
        'discord_username',
        'discord_member',
        'discord_avatar',
        'last_seen',
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

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function ($user) {
            $user->userPreferences()->create();
        });
    }

    public function fullName($format)
    {
        $preferences = $this->getUserPreferencesOrCreate();

        if ($format === 'FLC') {
            if ($preferences->name_format == 0) {
                return $this->id;
            } elseif ($preferences->name_format == 1) {
                return $this->fname . ' - ' . $this->id;
            } elseif ($preferences->name_format == 2) {
                return $this->fname . ' ' . substr($this->lname, 0, 1) . ' - ' . $this->id;
            } elseif ($preferences->name_format == 3) {
                return $this->fname . ' ' . $this->lname . ' - ' . $this->id;
            }
        } elseif ($format === 'FL') {
            if ($preferences->name_format == 0) {
                return $this->id;
            } elseif ($preferences->name_format == 1) {
                return $this->fname;
            } elseif ($preferences->name_format == 2) {
                return $this->fname . ' ' . substr($this->lname, 0, 1);
            } elseif ($preferences->name_format == 3) {
                return $this->fname . ' ' . $this->lname;
            }
        } elseif ($format === 'F') {
            if ($preferences->name_format == 0) {
                return $this->id;
            } elseif (in_array($preferences->name_format, [1, 2, 3])) {
                return $this->fname;
            }
        }

        return null;
    }

    public function userPreferences()
    {
        return $this->hasOne(UserPreference::class, 'user_id', 'id');
    }

    public function getUserPreferencesOrCreate()
    {
        if ($this->relationLoaded('userPreferences') && $this->userPreferences) {
            return $this->userPreferences;
        }

        $preferences = $this->userPreferences()->firstOrCreate([
            'user_id' => $this->id,
        ]);

        $this->setRelation('userPreferences', $preferences);

        return $preferences;
    }

    public function highestRole()
    {
        // If the user doesnt have a role, then give them one temporarily.
        if (count($this->roles) == 0) {
            // Assign them guest
            $this->assignRole('Pilot');
        }

        return $this->roles[0];
    }

    public function isFlying()
    {
        return $this->hasOne(Flights::class, 'cid', 'id')->withDefault();
    }
}