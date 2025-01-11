<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\SendNotiRefundMail;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\ProductItems;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class OrderController extends Controller
{

    public function showAllOrder()
    {
        $order = Order::all();
        return response()->json([
            'data' => $order
        ]);
    }

    public function totalMoney()
    {
        $totalOrders = Order::count();
        $totalMoney = Order::where('status', 'đã thanh toán')->sum('total_money');
        $pending = Order::where('status', 'chờ xử lí')->count();
        $delivering = Order::where('status', 'đang giao')->count();
        $complete = Order::where('status', 'đã hoàn thành')->count();
        $canceled = Order::where('status', 'bị hủy')->count();
        return response()->json([
            'Tổng đơn hàng' => $totalOrders,
            'Doanh thu' => $totalMoney,
            'Chờ xử lí' => $pending,
            'Đang giao' => $delivering,
            'Đã hoàn thành' => $complete,
            'Bị hủy' => $canceled,
        ]);
    }
    public function index(Request $request)
    {

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
                        $query->where('order.order_number', 'like', '%' . $search . '%')
                            ->where('order.shipping_addess', 'like', '%' . $search . '%')
                            ->where('order.billing_address', 'like', '%' . $search . '%')
                            ->where('vouchers.code', 'like', '%' . $search . '%');
                    });
                });
            }

            if ($sort) {
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

    public function detail(Request $request)
    {
        $id_order = $request->input('order_id');

        try {
            $order = Order::with([
                'user', 'voucher', 'orderDetails.productItem.product',
                'orderDetails.productItem.color',
                'orderDetails.productItem.size'
            ])
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

    public function edit(Request $request)
    {

        $validStatuses = [
            "1" => "chờ xử lí",
            "2" => "đã thanh toán",
            "3" => "đang giao",
            "4" => "đã hoàn thành",
            "5" => "bị hủy"
        ];

        try {
            $order = Order::findOrFail($request->input('order_id'));
            $user = User::where('id', $order->user_id)->first();
            $currentStatusIndex = array_search($order->status, $validStatuses);
            $nextStatusIndex = $request->input('status');

            $order->status = $validStatuses[$nextStatusIndex];

            if ($nextStatusIndex == 5 && $currentStatusIndex == 2) {
                $data_refund = $this->refundVNpay($order);
                $responseData = json_decode($data_refund->getContent());
                if ($responseData->code == 0) {
                    $order->status == $validStatuses[$nextStatusIndex];
                    $order->save();


                    Mail::to($user->email)->send(new SendNotiRefundMail($user, $order));

                    return response()->json([
                        'message' => 'Trạng thái đơn hàng đã được cập nhật, đơn hàng đã được hoàn tiền',
                        'data' => $order
                    ], 200);
                } else {
                    return response()->json([
                        'message' => 'Lỗi hoàn tiền',
                    ], 403);
                }
            } else {
                $order->save();
            }

            return response()->json([
                'message' => 'Trạng thái đơn hàng đã được cập nhật',
                'data' => $order
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Lỗi cập nhật trạng thái đơn hàng',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function delete($id)
    {
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

    public function refundVNpay($order)
    {
        $vnp_Url = "https://sandbox.vnpayment.vn/merchant_webapi/api/transaction";
        $vnp_HashSecret = 'DH63N76YR1W0OCH6YTF86GECMLWR99UJ';
        $vnp_IpAddr = $_SERVER['REMOTE_ADDR'];
        date_default_timezone_set('Asia/Ho_Chi_Minh');

        $ispTxnRequest = [
            'vnp_RequestId' => $this->generateRequestId(),
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'refund',
            'vnp_TmnCode' => 'F534FO6G',
            'vnp_TransactionType' => 02,
            'vnp_TxnRef' => "Thanh toán mã đơn hàng" . ' ' . $order->oder_number,
            'vnp_Amount' => $order->total_money * 100,
            'vnp_OrderInfo' => "Thanh toán mã đơn hàng" . ' ' . $order->oder_number . '-' . $order->id,
            'vnp_TransactionNo' => $order->transaction_no,
            'vnp_TransactionDate' => $order->transaction_date,
            'vnp_CreateDate' => date('YmdHis'),
            'vnp_CreateBy' => 'Admin',
            'vnp_IpAddr' => $vnp_IpAddr
        ];

        $dataHash = implode('|', [
            $ispTxnRequest['vnp_RequestId'],
            $ispTxnRequest['vnp_Version'],
            $ispTxnRequest['vnp_Command'],
            $ispTxnRequest['vnp_TmnCode'],
            $ispTxnRequest['vnp_TransactionType'],
            $ispTxnRequest['vnp_TxnRef'],
            $ispTxnRequest['vnp_Amount'],
            $ispTxnRequest['vnp_TransactionNo'],
            $ispTxnRequest['vnp_TransactionDate'],
            $ispTxnRequest['vnp_CreateBy'],
            $ispTxnRequest['vnp_CreateDate'],
            $ispTxnRequest['vnp_IpAddr'],
            $ispTxnRequest['vnp_OrderInfo'],
        ]);

        $checksum = hash_hmac('sha512', $dataHash, $vnp_HashSecret);
        $ispTxnRequest["vnp_SecureHash"] = $checksum;

        Log::info('VNPAY Refund Request Data:', $ispTxnRequest);

        try {

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->post($vnp_Url, $ispTxnRequest);

            $txnData = $response->json();

            Log::info('VNPAY Refund Response Data:', $txnData);

            if ($txnData) {
                return response()->json([
                    'message' => 'GD hoàn tiền thành công. Vui lòng truy cập Merchant Admin để kiểm tra giao dịch!',
                    'code' => 0
                ], 200);
            } else {
                return response()->json([
                    'message' => 'GD thất bại, đã có lỗi trong qua trình hoàn tiền. Vui lòng truy cập Merchant Admin để kiểm tra giao dịch!',
                ], 403);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }

    public function editBillStatus(Request $request)
    {

        $validStatuses = [
            "1" => "chờ xử lí",
            "2" => "đã thanh toán",
            "3" => "đang giao",
            "4" => "đã hoàn thành",
            "5" => "bị hủy"
        ];

        try {
            $order = Order::findOrFail($request->input('id'));
            // dd($order);
            $currentStatusIndex = array_search($order->status, $validStatuses);
            $nextStatusIndex = $request->input('status');

            // Kiểm tra xem trạng thái tiếp theo có hợp lệ
            if ($nextStatusIndex !== null && isset($validStatuses[$nextStatusIndex])) {
                // Điều kiện: Không cho phép chuyển từ trạng thái 4 sang 5 và từ 5 sang 4
                if (($currentStatusIndex == 3 && $nextStatusIndex == 5) ||
                    ($currentStatusIndex == 5 && $nextStatusIndex == 4)
                ) {
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
                                'product_id' => $product->id, // Thêm order_id
                                'order_id' => $orderDetail->order_id, // Thêm order_id
                                'product_item_id' => $productItem->id,
                                'product_name' => $product->name, // Lấy tên sản phẩm từ bảng Product
                                'product_image' => $product->image_product, // Lấy ảnh sản phẩm từ bảng Product
                                'color_id' => $productItem->color_id,
                                'color_name' => $productItem->color->name,
                                'color_hex' => $productItem->color->hex,  // Lấy tên màu sắc từ Color model
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
                    'id' => $order->id,
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

    public function getAllOrdersAdmin(Request $request)
    {
        try {
            $orders = Order::orderBy('created_at', 'desc')->get();

            if ($orders->isEmpty()) {
                return response()->json([
                    'message' => 'Không có đơn hàng nào',
                    'data' => []
                ], 200);
            }

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
                $user = $order->User;
                // Thêm thông tin chi tiết đơn hàng vào mảng
                $orderDetails[] = [
                    'id' => $order->id,
                    'user_id' => $order->user_id,
                    // 'user_name' => $user ? $user->name : null, // Thêm thông tin user (vd: tên)
                    'user_email' => $user ? $user->email : null, // Thêm email
                    'image_user' => $user ? $user->image_user : null, // Thêm email
                    'gender' => $user ? $user->gender : null, // Thêm email
                    'birthday' => $user ? $user->birthday : null, // Thêm email
                    'address' => $user ? $user->address : null, // Thêm email
                    'phone_number' => $user ? $user->phone_number : null, // Thêm email
                    'last_name' => $user ? $user->last_name : null, // Thêm email
                    'status_user' => $user ? $user->status : null, // Thêm email
                    'firt_name' => $user ? $user->firt_name : null, // Thêm email
                    'oder_number' => $order->oder_number,
                    'payment_method' => $order->payment_method,
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
                'message' => 'Danh sách tất cả đơn hàng',
                'data' => $orderDetails
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi lấy danh sách đơn hàng',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function confirmRefund(Request $request)
    {
        try {
            $reason = $request->reason;
            $order = Order::where('id', $request->order_id)->first();
            $user = User::where('id', $order->user_id)->first();
            if (empty($order)) {
                return response()->json([
                    'message' => 'Không tìm thấy order',
                ], 404);
            }
            $order->update([
                'note_admin' => $reason,
                'check_refund' => $request->check_refund,  // 0 Chờ xử lý, 1 xác nhận, 2 từ chối
                'status' => 'bị hủy'
            ]);
            Mail::to($user->email)->send(new SendNotiRefundMail($user, $order));
            return response()->json([
                'message' => 'Cập nhật trạng thái yêu cầu order thành công',
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error processing order: " . $e->getMessage());
            return response()->json([
                'message' => 'Đã xảy ra lỗi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getOrderById($orderId)
    {
        try {
            $order = Order::find($orderId);
            $user = User::find($order->user_id);

            // Kiểm tra nếu không tìm thấy đơn hàng
            if (!$order) {
                return response()->json([
                    'message' => 'Không tìm thấy đơn hàng',
                    'data' => null
                ], 404);
            }

            // Lấy danh sách chi tiết đơn hàng
            $details = OrderDetail::where('order_id', $order->id)
                ->get()
                ->map(function ($orderDetail) {
                    $productItem = ProductItems::where('id', $orderDetail->product_item_id)->first();
                    if ($productItem) {
                        $product = $productItem->product;
                        return [
                            'product_id' => $productItem->product_id,
                            'product_name' => $product->name,
                            'product_image' => $product->image_product,
                            'color_id' => $productItem->color_id,
                            'color_name' => $productItem->color->name,
                            'size_id' => $productItem->size_id,
                            'size_name' => $productItem->size->name,
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

            // Tạo cấu trúc dữ liệu chi tiết đơn hàng
            $orderDetails = [
                'id' => $order->id,
                'user_id' => $order->user_id,
                'firt_name' => $user ? $user->firt_name : null,
                'last_name' => $user ? $user->last_name : null,
                'phone_number' => $user ? $user->phone_number : null,
                'oder_number' => $order->oder_number,
                'payment_method' => $order->payment_method,
                'status' => $order->status,
                'total_money' => $order->total_money,
                'shipping_address' => $order->shipping_address,
                'billing_address' => $order->billing_address,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
                'products' => $details
            ];

            // Trả về thông tin chi tiết đơn hàng
            return response()->json([
                'message' => 'Thông tin chi tiết đơn hàng',
                'data' => $orderDetails
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi lấy thông tin chi tiết đơn hàng',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function generateRequestId($length = 10)
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    // public function listRefund () 
    // {
    //     try{
    //         $data = Order::whereIn('check_refund', [0,1,2])
    //             ->with(['user', 'voucher', 'orderDetails.productItem.product', 
    //             'orderDetails.productItem.color', 
    //             'orderDetails.productItem.size'])
    //             ->get();

    //         return response()->json([
    //             'message' => 'Thông tin danh sách yêu cầu hoàn',
    //             'data' => $data
    //         ], 200);
    //     }
    //     catch (Exception $e) {
    //     return response()->json([
    //         'message' => 'Lỗi lấy danh sách yêu cầu hoàn',
    //         'error' => $e->getMessage()
    //     ], 500);
    //     }
    // }

    public function listRefund()
    {
        try {
            // Lấy danh sách các đơn hàng có check_refund = 0
            $data = Order::where('check_refund', 0) // Điều kiện lọc
                ->with([
                    'user',
                    'voucher',
                    'orderDetails.productItem.product',
                    'orderDetails.productItem.color',
                    'orderDetails.productItem.size'
                ])
                ->get();

            return response()->json([
                'message' => 'Thông tin danh sách yêu cầu hoàn với check_refund = 0',
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Lỗi lấy danh sách yêu cầu hoàn',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getTopSellingProductsByMonth($year, $month)
    {
        try {
            // Lọc các đơn hàng có status "đã hoàn thành" và thuộc tháng và năm chỉ định
            $orders = Order::where('status', 'đã hoàn thành')
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->get();

            // Khởi tạo mảng để lưu trữ thông tin sản phẩm thống kê
            $productsStats = [];

            // Duyệt qua tất cả các đơn hàng đã hoàn thành trong tháng
            foreach ($orders as $order) {
                // Lấy chi tiết đơn hàng của từng đơn
                $orderDetails = OrderDetail::where('order_id', $order->id)->get();

                // Duyệt qua các chi tiết đơn hàng
                foreach ($orderDetails as $orderDetail) {
                    $productItem = ProductItems::find($orderDetail->product_item_id);

                    if ($productItem) {
                        // Kiểm tra nếu sản phẩm đã có trong mảng thống kê
                        if (isset($productsStats[$productItem->product_id])) {
                            // Cộng dồn số lượng và doanh thu
                            $productsStats[$productItem->product_id]['quantity'] += $orderDetail->quantity;
                            $productsStats[$productItem->product_id]['total_revenue'] += $orderDetail->quantity * $orderDetail->price;
                        } else {
                            $product = $productItem->product;
                            // Thêm thông tin sản phẩm vào mảng
                            $productsStats[$productItem->product_id] = [
                                'product_id' => $productItem->product_id,
                                'product_name' => $product->name,
                                'product_image' => $product->image_product,
                                'quantity' => $orderDetail->quantity,
                                'total_revenue' => $orderDetail->quantity * $orderDetail->price,
                                'month' => $month,
                                'year' => $year
                            ];
                        }
                    }
                }
            }

            // Chuyển mảng sản phẩm thành một mảng sắp xếp theo số lượng bán ra giảm dần
            $sortedProducts = collect($productsStats)->sortByDesc('quantity')->values()->toArray();

            // Trả về kết quả thống kê sản phẩm theo tháng
            return response()->json([
                'message' => 'Top selling products for ' . $month . '/' . $year,
                'data' => $sortedProducts,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error fetching top selling products by month',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function cancelOrder(Request $request)
    {
        try {
            // Lấy thông tin từ body request
            $orderId = $request->input('order_id'); // Lấy orderId từ body request
            $cancelNote = $request->input('cancel_note'); // Lấy cancel_note từ body request

            // Xác thực người dùng
            $user = auth()->user();
            $userId = $user->id;

            // Lấy đơn hàng cần hủy bằng ID và kiểm tra quyền sở hữu
            $order = Order::where('id', $orderId)->where('user_id', $userId)->first();

            // Kiểm tra nếu đơn hàng không tồn tại
            if (!$order) {
                return response()->json([
                    'message' => 'Đơn hàng không tồn tại hoặc bạn không có quyền hủy đơn hàng này.'
                ], 404);
            }

            // Kiểm tra nếu đơn hàng đã được giao hoặc đã hoàn tất, không thể hủy
            if (in_array($order->status, ['đã thanh toán', 'đang giao', 'đã hoàn thành'])) {
                return response()->json([
                    'message' => 'Không thể hủy đơn hàng đã hoàn tất hoặc đã được giao.'
                ], 400);
            }

            // Cập nhật trạng thái đơn hàng và ghi chú hủy
            $order->status = 'bị hủy'; // Đặt trạng thái là đã hủy
            $order->cancel_note = $cancelNote; // Lưu ghi chú lý do hủy
            $order->save();

            // Trả về kết quả thành công
            return response()->json([
                'message' => 'Đơn hàng đã được hủy thành công.',
                'data' => $order
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi hủy đơn hàng',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
