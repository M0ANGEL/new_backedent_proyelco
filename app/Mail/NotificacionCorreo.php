<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NotificacionCorreo extends Mailable
{
    use Queueable, SerializesModels;

    public $detalles;

    public function __construct($detalles)
    {
        $this->detalles = $detalles;
    }

    public function build()
    {
        return $this->subject('Asunto desde Outlook')
                    ->view('emails.notificacion');
    }
}

