<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function paymentVnPay(Request $request)
    {
        $vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
        $vnp_HashSecret = "79WJ46ZSOP99QOE6IL38N4H2UR1G5JH8";

        $vnp_Command =  "pay";
        $vnp_TmnCode = "F534FO6G";
        $vnp_Amount = $request->amount*100;
        $vnp_CurrCode = "VND";
        $vnp_IpAddr = "http://127.0.0.1:8000";
        $vnp_Locale = "vn";
        $vnp_OrderInfo = "Thanh toán mã đơn hàng" .' '. $request->order_number .'-'. $request->order_id;
        $vnp_OrderType = "billpayment";
        $vnp_Returnurl = "http://127.0.0.1:8000/api/return_paymentVnPay";
        // $vnp_TxnRef = "Thanh toán mã đơn hàng" .' '. $request->order_number;
        $vnp_TxnRef = 'TXN-' . $request->order_id . '-' . time(); // Mã tham chiếu duy nhất
        $vnp_IpAddr = $_SERVER['REMOTE_ADDR'];

        // dd($vnp_ReturnUrl);
        $inputData = array(
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $vnp_TmnCode,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $vnp_IpAddr,
            "vnp_Locale" => $vnp_Locale,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => $vnp_OrderType,
            "vnp_ReturnUrl" => $vnp_Returnurl,
            "vnp_TxnRef" => $vnp_TxnRef,
        );

        // dd($inputData);

        if (isset($vnp_BankCode) && $vnp_BankCode != "") {
            $inputData['vnp_BankCode'] = $vnp_BankCode;
        }
        if (isset($vnp_Bill_State) && $vnp_Bill_State != "") {
            $inputData['vnp_Bill_State'] = $vnp_Bill_State;
        }

        ksort($inputData);
        $query = "";
        $i = 0;
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashdata .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }
        
        $vnp_Url = $vnp_Url . "?" . $query;
        if (isset($vnp_HashSecret)) {
            $vnpSecureHash =   hash_hmac('sha512', $hashdata, $vnp_HashSecret);//  
            $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
        }


        return response()->json([
            'message' => 'Tạo thanh toán thành công',
            'url_payment' => $vnp_Url
        ], 200);

    }


    public function returnPaymentVnPay(Request $request)
    { 
        $vnp_SecureHash = $_GET['vnp_SecureHash'];
        $vnp_HashSecret = "79WJ46ZSOP99QOE6IL38N4H2UR1G5JH8";
        $inputData = array();
        foreach ($_GET as $key => $value) {
            if (substr($key, 0, 4) == "vnp_") {
                $inputData[$key] = $value;
            }
        }   
        
        unset($inputData['vnp_SecureHash']);
        ksort($inputData);
        $i = 0;
        $hashData = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashData = $hashData . '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashData = $hashData . urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
        }

        $secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);
        if ($secureHash == $vnp_SecureHash) {
            if(!$_GET['vnp_ResponseCode']) {
                return response()->json([
                    'message' => 'Không tồn tại phiên thanh toán',
                ], 403);
            }

            $message_array = array(
                "00"=>	"Giao dịch thành công",
                "07"=>	"Trừ tiền thành công. Giao dịch bị nghi ngờ (liên quan tới lừa đảo, giao dịch bất thường).",
                "09"=>	"Giao dịch không thành công do: Thẻ/Tài khoản của khách hàng chưa đăng ký dịch vụ InternetBanking tại ngân hàng.",
                "10"=>	"Giao dịch không thành công do: Khách hàng xác thực thông tin thẻ/tài khoản không đúng quá 3 lần",
                "11"=>	"Giao dịch không thành công do: Đã hết hạn chờ thanh toán. Xin quý khách vui lòng thực hiện lại giao dịch.",
                "12"=>	"Giao dịch không thành công do: Thẻ/Tài khoản của khách hàng bị khóa.",
                "13"=>	"Giao dịch không thành công do Quý khách nhập sai mật khẩu xác thực giao dịch (OTP). Xin quý khách vui lòng thực hiện lại giao dịch.",
                "24"=>	"Giao dịch không thành công do: Khách hàng hủy giao dịch",
                "51"=>	"Giao dịch không thành công do: Tài khoản của quý khách không đủ số dư để thực hiện giao dịch.",
                "65"=>	"Giao dịch không thành công do: Tài khoản của Quý khách đã vượt quá hạn mức giao dịch trong ngày.",
                "75"=>	"Ngân hàng thanh toán đang bảo trì.",
                "79"=>	"Giao dịch không thành công do: KH nhập sai mật khẩu thanh toán quá số lần quy định. Xin quý khách vui lòng thực hiện lại giao dịch",
                "99"=>	"Các lỗi khác (lỗi còn lại, không có trong danh sách mã lỗi đã liệt kê)",
            );

            if (isset($message_array[$_GET['vnp_ResponseCode']])) {
                $order_info = $_GET['vnp_OrderInfo'];
                $parts = explode('-',$order_info);
                $order_id = end($parts);
                
                DB::table('order')->where('id', $order_id)->update(['status' => 'đã thanh toán']);

                return response()->json([
                    'message' => $message_array[$_GET['vnp_ResponseCode']],
                    'code' => $_GET['vnp_ResponseCode'],
                    'order_id' => $order_id
                ], 200);
            };
        }else {
            return response()->json([
                'message' => 'Chữ kí không hợp lệ',
            ], 403);
        }
    }
}
    