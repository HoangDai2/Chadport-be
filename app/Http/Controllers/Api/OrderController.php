<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\ProductItems;
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

    public function getOrdersByUserAndStatus(Request $request)
    {
        try {
            // Xác thực JWT và lấy thông tin user từ token
            $user = auth()->user(); // Lấy thông tin user từ JWT
            $userId = $user->id;

            // Lấy trạng thái từ request
            $status = $request->input('status'); // Trạng thái đơn hàng

            // Nếu trạng thái là "Tất cả" hoặc không truyền trạng thái, lấy tất cả đơn hàng của user
            $query = Order::query()->where('user_id', $userId);

            if ($status && $status !== "Tất cả") {
                $query->where('status', $status);
            }

            // Lấy danh sách đơn hàng
            $orders = $query->orderBy('created_at', 'desc')->get();

            // Kiểm tra nếu không có đơn hàng nào
            if ($orders->isEmpty()) {
                return response()->json([
                    'message' => 'Không có đơn hàng nào với trạng thái: ' . ($status ?? 'Tất cả') . ' và user_id: ' . $userId,
                    'data' => []
                ], 404);
            }

            // Lấy thông tin sản phẩm từ bảng ProductItems qua OrderDetail
            $orderDetails = [];
            foreach ($orders as $order) {
                // Lấy danh sách chi tiết đơn hàng
                $details = OrderDetail::where('order_id', $order->id)
                    ->get()
                    ->map(function ($orderDetail) {
                        // Lấy thông tin sản phẩm từ bảng ProductItems qua product_item_id
                        $productItem = ProductItems::where('id', $orderDetail->product_item_id)->first();

                        // Kiểm tra nếu không tìm thấy sản phẩm
                        if ($productItem) {
                            $product = $productItem->product;
                            return [
                                'product_id' => $productItem->product_id,
                                'product_name' => $product->name, // Lấy tên sản phẩm từ bảng Product
                                'product_image' => $product->image_product, // Lấy ảnh sản phẩm từ bảng Product
                                'color_id' => $productItem->color_id,
                                'color_name' => $productItem->color->name,  // Lấy tên màu sắc từ Color model
                                'size_id' => $productItem->size_id,
                                'size_name' => $productItem->size->name,  // Lấy tên kích thước từ Size model
                                'description' => $productItem->description,
                                'quantity' => $orderDetail->quantity,
                                'price' => $orderDetail->price,
                            ];
                        } else {
                            return [
                                'message' => 'Không tìm thấy sản phẩm với product_item_id: ' . $orderDetail->product_item_id
                            ];
                        }
                    });

                // Thêm thông tin chi tiết đơn hàng vào mảng
                $orderDetails[] = [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total_money' => $order->total_money,
                    'shipping_address' => $order->shipping_address,
                    'billing_address' => $order->billing_address,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,
                    'products' => $details
                ];
            }

            // Trả về danh sách đơn hàng với thông tin sản phẩm
            return response()->json([
                'message' => 'Danh sách đơn hàng với trạng thái: ' . ($status ?? 'Tất cả') . ' và user_id: ' . $userId,
                'data' => $orderDetails
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi lấy danh sách đơn hàng',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}