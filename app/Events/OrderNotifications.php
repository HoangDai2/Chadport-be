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
    public $user_id;

    /**
     * Create a new event instance.
     *
     * @param string $message
     * @param string $orderNumber
     * @return void
     */
    public function __construct($message, $orderNumber,$user_id)
    {
        $this->message = $message;
        $this->orderNumber = $orderNumber;
        $this->userId = $user_id;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('notifications-order.'. $this->user_id); // Kênh riêng cho người dùng);
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