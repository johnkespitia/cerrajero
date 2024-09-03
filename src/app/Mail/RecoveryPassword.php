<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RecoveryPassword extends Mailable
{
    use Queueable, SerializesModels;

    protected $token;
    protected $name;
    protected $email;
    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($token, $name , $email)
    {
        $this->token = $token;
        $this->name = $name;
        $this->email = $email;
    }


    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $link =  env("SITE_URL")."password-reset/{$this->email}/{$this->token}" ;
        $this->subject("Recuperación de contraseña - ".env("APP_NAME"));
        return $this->view('emails.recovery',["link"=>$link, "name"=>$this->name]);
    }
}
