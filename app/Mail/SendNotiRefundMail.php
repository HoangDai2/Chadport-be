<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendNotiRefundMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $order;

    public function __construct($user, $order)
    {
        $this->user = $user;
        $this->order = $order;
    }

    public function build()
    {
        return $this->subject('Thông báo yêu cầu đơn hàng bị hủy từ shop')
                    ->view('mail.sendnotirefund')
                    ->with([
                        'user' => $this->user,
                        'order' => $this->order,
                    ]);
    }
}