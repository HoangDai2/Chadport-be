<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function showAllOrder()  {
        $order = Order::all();
        return response()->json([
            'data'=> $order
        ]);
    }

    public function totalMoney() {
        $totalOrders = Order::count();
        $totalMoney = Order::where('status', 'đã thanh toán')->sum('total_money');
        $pending = Order::where('status', 'chờ xử lí')->count();
        $delivering = Order::where('status', 'đang giao')->count();
        $complete = Order::where('status', 'đã hoàn thành')->count();
        $canceled = Order::where('status', 'bị hủy')->count();
        return response()->json([
            'Tổng đơn hàng'=>$totalOrders,
            'Doanh thu'=> $totalMoney,
            'Chờ xử lí'=>$pending,
            'Đang giao'=>$delivering,
            'Đã hoàn thành'=>$complete,
            'Bị hủy'=>$canceled,
        ]);
    }
}
