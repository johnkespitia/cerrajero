<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ContactUsMail extends Mailable
{
    use Queueable, SerializesModels;

    private $_messageInfo;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($messageInfo)
    {
        $this->_messageInfo = $messageInfo;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->subject("Mensaje desde la página de Campo Verde");
        Log::info(print_r($this->_messageInfo,1));
        return $this->view('emails.contactus', [
            "messageInfo" => $this->_messageInfo
        ]);
    }
}
