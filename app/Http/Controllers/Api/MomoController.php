<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MomoController extends Controller
{
    public function createPayment(Request $request)
    {
        $endpoint = "https://test-payment.momo.vn/v2/gateway/api/create";
        $partnerCode = "MOMO";
        $accessKey = "F8BBA842ECF85";
        $secretKey = "K951B6PE1waDMi640xX08PD3vg6EkVlz";
        $orderInfo = "Thanh toán đơn hàng";
        $amount = $request->amount; 
        $orderId = time(); 
        $redirectUrl = 'http://127.0.0.1:8000/api/payment-return';
        $ipnUrl = 'http://127.0.0.1:8000/api/payment-return';
        $requestId = time(); 
        $extraData = "";     

        $rawHash = "accessKey=$accessKey&amount=$amount&extraData=$extraData&ipnUrl=$ipnUrl&orderId=$orderId&orderInfo=$orderInfo&partnerCode=$partnerCode&redirectUrl=$redirectUrl&requestId=$requestId&requestType=captureWallet";
        $signature = hash_hmac("sha256", $rawHash, $secretKey);
    
        $data = [
            'partnerCode' => $partnerCode,
            'partnerName' => "MoMo Payment",
            'storeId' => "MOMOStore",
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $orderId,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $redirectUrl,
            'ipnUrl' => $ipnUrl,
            'lang' => 'vi',
            'extraData' => $extraData,
            'requestType' => 'captureWallet',
            'signature' => $signature,
        ];

        

        $response = Http::post($endpoint, $data);
    
        if ($response->successful()) {
            $result = $response->json();
    
            return response()->json([
                'status' => 'success',
                'payUrl' => $result['payUrl'], 
                'orderId' => $orderId,
                'amount' => $amount,
            ], 200);
        }
    

        return response()->json([
            'status' => 'error',
            'message' => 'Không thể tạo yêu cầu thanh toán',
        ], 500);

       
    }

    public function paymentReturn(Request $request) {

        $data = $request->all();

        return response()->json([
            'data'=>$data,
        ]);
        
        
    }
}
