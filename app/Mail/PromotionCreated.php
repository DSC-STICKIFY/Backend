<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PromotionCreated extends Mailable
{
    use Queueable, SerializesModels;

    public $promotion;

    public function __construct($promotion)
    {
        $this->promotion = $promotion;
    }

    public function build()
    {
        return $this->subject('🎉 New Promotion: ' . $this->promotion['name'])
                    ->view('emails.promotion');
    }
}
