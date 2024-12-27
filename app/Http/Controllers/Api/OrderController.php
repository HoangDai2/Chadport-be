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
            $user = User::where('id', $order->user_id)->first();
            $currentStatusIndex = array_search($order->status, $validStatuses);
            $nextStatusIndex = $request->input('status');
    
            $order->status = $validStatuses[$nextStatusIndex];

            if($nextStatusIndex == 5 && $currentStatusIndex == 2){
                $data_refund = $this->refundVNpay($order);
                $responseData = json_decode($data_refund->getContent());
                if($responseData->code == 0) {
                    $order->status == $validStatuses[$nextStatusIndex];
                    $order->save();

                    return response()->json([
                        'message' => 'Trạng thái đơn hàng đã được cập nhật, đơn hàng đã được hoàn tiền',
                        'data' => $order
                    ], 200);

                    Mail::to($user->email)->send(new SendNotiRefundMail($user, $order));
                }else {
                    return response()->json([
                        'message' => 'Lỗi hoàn tiền',
                    ], 403);
                }
            }else{
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
            'vnp_TxnRef' => "Thanh toán mã đơn hàng" .' '. $order->oder_number,
            'vnp_Amount' => $order->total_money*100,
            'vnp_OrderInfo' => "Thanh toán mã đơn hàng" .' '. $order->oder_number .'-'. $order->id,
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
    
            if($txnData) {
                return response()->json([
                    'message' => 'GD hoàn tiền thành công. Vui lòng truy cập Merchant Admin để kiểm tra giao dịch!',
                    'code' => 0
                ], 200);
            }else{
                return response()->json([
                    'message' => 'GD thất bại, đã có lỗi trong qua trình hoàn tiền. Vui lòng truy cập Merchant Admin để kiểm tra giao dịch!',
                ], 403);
            }

        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }
    
    private function generateRequestId($length = 10) {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $charactersLength = strlen($characters);
        $randomString = '';
    
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
    
        return $randomString;
    }

    public function listRefund(Request $request)
    {
        try{
            $orders = Order::whereIn('check_refund', [0,1,2])->get();

            $formattedOrders = $orders->map(function($order) {
                $note = json_decode($order->note_user, true);

                $reason = $note['reason'] ?? null;
                $accountInfo = $note['account_info'] ?? [];
                $status = $order->check_refund === 0 ? 'Chờ xử lý' : 'Đã hoàn tiền';

                return [
                    'id' => $order->id,
                    'order_number' => $order->oder_number,
                    'total_order' => $order->total_money,
                    'reason' => $reason,
                    'accountInfo' => $accountInfo,
                    'check_refund' => $order->check_refund,
                    'status' => $status,
                ];
            });

            return response()->json([
                'message' => 'Danh sách đơn hàng yêu cầu hoàn tiền',
                'data' => $formattedOrders
            ], 200);

        } catch (\Exception $e) {
            Log::error("Error processing order: " . $e->getMessage());

            return response()->json([
                'message' => 'Đã xảy ra lỗi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function confirmRefund(Request $request)
    {
        try{

            $reason = $request->reason;
            $order = Order::where('id', $request->order_id)->first();

            $user = User::where('id', $order->user_id)->first();

            if(empty($order)){
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
}