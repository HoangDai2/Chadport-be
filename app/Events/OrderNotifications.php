<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class OrderNotifications implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public $message;
    public $orderNumber;

    /**
     * Create a new event instance.
     *
     * @param string $message
     * @param string $orderNumber
     * @return void
     */
    public function __construct($message, $orderNumber)
    {
        $this->message = $message;
        $this->orderNumber = $orderNumber;
    }

    public function broadcastOn()
    {
        return new Channel('notifications-order');
    }

    public function broadcastAs()
    {
        return 'order-placed-success';
    }
    
    public function broadcastWith()
    {
        return [
            'Đơn hàng ' .  $this->orderNumber . ' đã được đặt thành công!'
        ];
    }
}