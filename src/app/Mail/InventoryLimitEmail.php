<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InventoryLimitEmail extends Mailable
{
    use Queueable, SerializesModels;

    protected $inventoryInput;
    protected $currentStock;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct( $inventoryInput, $currentStock)
    {
        $this->inventoryInput = $inventoryInput;
        $this->currentStock = $currentStock;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Limite de inventario alcanzado')
            ->view('emails.inventory_limit', ["inventoryInput"=>$this->inventoryInput, "currentStock"=>$this->currentStock]);
    }
}
