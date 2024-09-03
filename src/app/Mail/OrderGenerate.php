<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderGenerate extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    private $order;
    public function __construct($order, $user, $address)
    {
        $this->order = $order;
        $this->user = $user;
        $this->address = $address;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $link =  env("SITE_URL")."account/order/{$this->order->id}" ;
        $this->subject("Gracias por comprar en Campo Verde - Orden # {$this->order->id}");
        return $this->view('emails.ordergen',["order"=>$this->order, "user"=>$this->user, "address"=> $this->address, "link"=>$link]);
    }
}
