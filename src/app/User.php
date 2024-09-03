<?php

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use App\Notifications\MailResetPasswordNotification;
use App\Mail\RecoveryPassword;
use Illuminate\Support\Facades\Mail;
class User extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password', "api_token","business_id","rol_id","phone"
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function generateToken()
    {
        $this->api_token = \uniqid("",true);
        $this->save();

        return $this->api_token;
    }

    public function rol()
    {
        return $this->belongsTo('App\Rol');
    }


    public function customer(){
        return $this->hasOne("App\Customer" , "user_id");
    }


    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        Mail::to($this->email)->send(new RecoveryPassword($token, $this->name, $this->email));
    }
}
