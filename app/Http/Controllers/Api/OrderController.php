<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request) {

        $search = $request->input('search');
        $sort = $request->input('sort');
        try {
            $data_order = DB::table('order')
                ->leftjoin('users', 'users.id', '=', 'order.user_id')
                ->leftjoin('vouchers', 'vouchers.id', '=', 'order.voucher_id')
                ->select('order.*', 'users.firt_name', 'users.last_name', 'vouchers.code');

                if ($search) {
                    $data_order->when($search, function ($query, $search) {
                        $query->where(function ($query) use ($search) {
                            $query->where('order.order_number', 'like', '%'.$search.'%')
                            ->where('order.shipping_addess', 'like', '%'.$search.'%')
                            ->where('order.billing_address', 'like', '%'.$search.'%')
                            ->where('vouchers.code', 'like', '%'.$search.'%');
                        });
                    });
                }

            if($sort) {
                $data_order->orderBy('id', $sort);
            }

            $data = $data_order->paginate(20);

            return response()->json([
                'message' => 'Danh sách đơn hàng',
                'data' => $data
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Lỗi lấy danh sách đơn hàng',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function detail(Request $request) {
        $id_order = $request->input('order_id');
    
        try {
            $order = Order::with(['user', 'voucher', 'orderDetails.productItem.product', 
                               'orderDetails.productItem.color', 
                               'orderDetails.productItem.size'])
            ->where('id', $id_order)
            ->first();
    
            return response()->json([
                'message' => 'Chi tiêt đơn hàng',
                'data' => $order
            ], 200);
    
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Lỗi lấy chi tiết đơn hàng',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function edit(Request $request) {

        $validStatuses = [
            "1" => "chờ xử lí",
            "2" => "đã thanh toán",
            "3" => "đang giao",
            "4" => "đã hoàn thành",
            "5" => "bị hủy"
        ];
    
        try {
            $order = Order::findOrFail($request->input('order_id'));
    
            $currentStatusIndex = array_search($order->status, $validStatuses);
            $nextStatusIndex = $request->input('status');
    
            // Kiểm tra xem trạng thái tiếp theo có hợp lệ
            if ($nextStatusIndex !== null && isset($validStatuses[$nextStatusIndex])) {
                // Điều kiện: Không cho phép chuyển từ trạng thái 4 sang 5 và từ 5 sang 4
                if (($currentStatusIndex == 3 && $nextStatusIndex == 5) || 
                    ($currentStatusIndex == 5 && $nextStatusIndex == 4)) {
                    return response()->json([
                        'message' => 'Không thể chuyển trạng thái từ "đã hoàn thành" sang "bị hủy" hoặc từ "bị hủy" sang "đã hoàn thành".',
                    ], 400);
                }
    
                // Kiểm tra nếu trạng thái tiếp theo hợp lệ và không quay ngược lại
                if ($currentStatusIndex === false || $nextStatusIndex == $currentStatusIndex + 1) {
                    $order->status = $validStatuses[$nextStatusIndex];
                    $order->save();
            
                    return response()->json([
                        'message' => 'Trạng thái đơn hàng đã được cập nhật',
                        'data' => $order
                    ], 200);
                } else {
                    return response()->json([
                        'message' => 'Không thể chuyển trạng thái đơn hàng, vui lòng kiểm tra lại.',
                    ], 400);
                }
            } else {
                return response()->json([
                    'message' => 'Trạng thái không hợp lệ.',
                ], 400);
            }
    
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Lỗi cập nhật trạng thái đơn hàng',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function delete($id) {
        try {
            $order = Order::findOrFail($id);
            $order->delete();
    
            return response()->json([
                'message' => 'Đơn hàng đã được xóa',
                'data' => $order
            ], 200);
    
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Lỗi xóa đơn hàng',
                'error' => $e->getMessage()
            ], 500);
        }
    }

     
}