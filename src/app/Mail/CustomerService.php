<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CustomerService extends Mailable
{
    use Queueable, SerializesModels;

     /**
     * Create a new message instance.
     *
     * @return void
     */

    private $order;
    private $user;
    private $ticket;
    private $message;
    public function __construct($order, $user, $ticket, $message)
    {
        $this->order = $order;
        $this->user = $user;
        $this->ticket = $ticket;
        $this->message = $message;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->subject("Se ha presentado una solicitud para la orden # {$this->order}");
        return $this->view('emails.customer_service',[
            "order"=>$this->order, 
            "user"=>$this->user, 
            "ticket"=>$this->ticket, 
            "message"=>$this->message
        ]);
    }
}
