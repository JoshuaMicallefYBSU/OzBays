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
        'discord_avatar'
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

    public function fullName($format)
    {
        if ($format == 'FLC') {
            if($this->display_last_name == 0) {
                return $this->fname.' - '.$this->id;
            } elseif ($this->display_last_name == 1) {
                return $this->fname.' '.$this->lname.' - '.$this->id;
            } elseif($this->display_last_name == 2){
                return $this->fname.' '.substr($this->lname, 0, 1).' - '.$this->id;
            }
        } elseif ($format === 'FL') {
            if($this->display_last_name == 0) {
                return $this->fname;
            } elseif ($this->display_last_name == 1) {
                return $this->fname.' '.$this->lname;
            } elseif($this->display_last_name == 2){
                return $this->fname.' '.substr($this->lname, 0, 1);
            }
        } elseif ($format === 'F') {
            return $this->fname;
        }

        return null;
    }

    public function highestRole()
    {
        //If the user doesnt have a role, then give them one temporarily.
        if (count($this->roles) == 0) {
            //Assign them guest
            $this->assignRole('Member');
        }

        return $this->roles[0];
    }

    public function isFlying()
    {
        return $this->hasOne(Flights::class, 'cid', 'id')->withDefault();
    }
}
