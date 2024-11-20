<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart_item;
use App\Models\Product;
use App\Models\Voucher;
use Database\Seeders\Voucher_usedSeeder;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CartController extends Controller
{
    public $cart;
    public $voucher;

    // public function __construct()
    // {
    //     $this->middleware(function ($request, $next) {
    //         $this->cart = \Session::get('cart');
    //         $this->voucher = \Session::get('voucher');
    //         return $next($request);
    //     });
    // }

    public function addToCart(Request $request)
    {
        try {
            // Xác thực request
            $request->validate([
                'product_id' => 'required|integer|exists:products,id',
                'quantity' => 'required|integer|min:1',
            ]);

            // Lấy user đã đăng nhập
            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'Bạn cần đăng nhập để thêm sản phẩm vào giỏ hàng'], 401);
            }

            // Lấy thông tin sản phẩm
            $product = Product::find($request->product_id);
            if (!$product || $product->quantity < $request->quantity) {
                return response()->json(['message' => 'Sản phẩm không đủ số lượng'], 400);
            }
            // dd($product);
            // Lấy hoặc tạo giỏ hàng cho user
            $cart = Cart_item::firstOrCreate(
                ['user_id' => $user->id], // Điều kiện để tìm
                ['created_at' => now(), 'updated_at' => now()] // Nếu không tồn tại, sẽ tạo mới
            );

            // Kiểm tra xem sản phẩm đã tồn tại trong giỏ chưa
            $cartItem = Cart_item::where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->first();

            if ($cartItem) {
                // Nếu đã tồn tại, cập nhật số lượng và giá
                $newQuantity = $cartItem->quantity + $request->quantity;

                if ($newQuantity > $product->quantity) {
                    return response()->json(['message' => 'Số lượng sản phẩm đã đạt mức tối đa'], 400);
                }

                $cartItem->update([
                    'quantity' => $newQuantity,
                    'price' => $product->price * $newQuantity,
                ]);
            } else {
                // Nếu chưa tồn tại, thêm sản phẩm mới vào giỏ
                Cart_item::create([
                    'cart_id' => $cart->id,
                    'user_id' => $user->id, // Đảm bảo user_id luôn được gán
                    'product_id' => $product->id,
                    'quantity' => $request->quantity,
                    'price' => $product->price * $request->quantity,
                ]);
            }

            return response()->json([
                'message' => 'Thêm sản phẩm vào giỏ hàng thành công',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error adding to cart: ' . $e->getMessage());
            return response()->json(['message' => 'Đã xảy ra lỗi. Vui lòng thử lại sau.'], 500);
        }
    }



    public function get_cart($cartId)
    {
        // Lấy các mục giỏ hàng và thông tin liên quan
        $cartItems = Cart_item::with('productVariant')
            ->where('cart_id', $cartId)
            ->get();
    
        // Tính tổng giá trị giỏ hàng
        $totalPrice = $cartItems->sum(function ($item) {
            return $item->quantity * $item->price;
        });
    
        // Trả về JSON
        return response()->json([
            'message' => 'Danh sách các mục trong giỏ hàng.',
            'cart_items' => $cartItems,
            'total_price' => $totalPrice,
        ], 200);
    }

    public function deleteProductCart(Request $request)
    {
        try {
            foreach ($this->cart as $key => $val) {
                if ($val['product_id'] == $request->product_id) {
                    unset($this->cart[$key]);
                }
            }
            \Session::put('cart', $this->cart);
            \Session::save();
            $this->updateTotalPrice();

            return $this->get_cart();
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function updateQuantityCart(Request $request)
    {
        $product_id = $request->product_id;
        $option = $request->option;

        foreach ($this->cart as $key => $val) {
            if ($val['product_id'] === $product_id) {
                if ($option === 'down' && $val['product_quantity'] === 1) {
                    return $this->deleteProductCart($request);
                }
    
                $this->cart[$key]['product_quantity'] += $option === 'up' ? 1 : -1;
    
                $this->cart[$key]['cart_amount'] = $this->cart[$key]['cart_price'] * $this->cart[$key]['product_quantity'];
                $this->cart[$key]['cart_amount_sale'] = $this->cart[$key]['cart_price_sale'] 
                    ? $this->cart[$key]['cart_price_sale'] * $this->cart[$key]['product_quantity'] 
                    : $this->cart[$key]['cart_price'] * $this->cart[$key]['product_quantity'];
    
                \Session::put('cart', $this->cart);
                $this->updateTotalPrice();
                \Session::save();
    
                break;
            }
        }
        return $this->get_cart();
    }

    public function payment(Request $request)
    {
        try {
            DB::beginTransaction();

            $user_id = auth()->user()->user_id;
            $data_cart = $this->cart;
            $this->cart = session()->get('cart', []);
            foreach ($this->cart as $product) {
                if (is_array($product) && isset($product['product_id'], $product['product_quantity'])) {
                    $data = Product::find($product['product_id']);

                    if ($data && ($data->quantity == 0 || $data->quantity < intval($product['product_quantity']))) {
                        $this->deleteSS();
                        return response()->json([
                            'message' => 'Đặt hàng thất bại do sản phẩm đã hết hàng hoặc số lượng hàng không đủ'
                        ], 400);
                    }
                }
            }

            $orderNumber = 'ORDER-' . strtoupper(uniqid());

            $total_money = \Session::get('total_voucher_cart') ? \Session::get('total_voucher_cart') : \Session::get('total_cart');

            $order = DB::table('order')->insertGetId([
                'voucher_id' => $this->voucher ? $this->voucher->id : null,
                'user_id' => $user_id,
                'oder_number' => $orderNumber,
                'payment_method' => $request->input('payment_method'),
                'total_money' => $total_money,
                'shipping_address' => $request->input('shipping_address'),
                'billing_address' => $request->input('billing_address'),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            foreach ($data_cart as $product) {
                if (is_array($product)) {
                    $productData = Product::find($product['product_id']);

                    $data_order_detail =DB::table('order_detail')->insert([
                        'order_id' => $order,
                        'product_id' => $product['product_id'],
                        'quantity' => intval($product['product_quantity']),
                        'price' => $productData ? $productData->price : 0,
                        'total_price' => intval($product['product_quantity']) * ($productData->price_sale ? $productData->price_sale : $productData->price),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    if ($productData) {
                        $productData->quantity -= intval($product['product_quantity']);
                        $productData->save();
                    }
                }
            }

            if ($this->voucher) {
                $data_voucher_used = DB::table('vocher_used')->insert([
                    'voc_id' => $this->voucher->id,
                    'user_id' => $user_id,
                    'created_at' => now(),
                    'expiration_date' => now()->addDays(30),
                ]);
            }

            DB::commit();
            $this->deleteSS();

            return response()->json([
                'message' => 'Đơn hàng của bạn đã được đặt thành công',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error processing order: " . $e->getMessage());

            return response()->json([
                'message' => 'Đã xảy ra lỗi trong quá trình đặt hàng, vui lòng thử lại sau.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function addCouponCart(Request $request)
    {
        $voucher = Voucher::where('id', $request->voucher_id)->first();

        if (!$voucher) {
            return response()->json([
                'message' => 'Không tìm thấy voucher',
            ], 404);
        }

        $check_user_voucher_exists = DB::table('vocher_used')
            ->where('voc_id', $request->voc_id)
            ->where('user_id', auth()->user()->user_id)
            ->exists();

            dd($check_user_voucher_exists);
        if ($check_user_voucher_exists) {
            return response()->json([
                'message' => 'Bạn đã sử dụng voucher này rồi',
            ], 403);
        }

        $data_cart = \Session::get('total_cart');

        if (!$data_cart) {
            return response()->json([
                'message' => 'Giỏ hàng không hợp lệ',
            ], 400);
        }

        if ($voucher->discount_type === 'percentage') {
            $price_voucher = $data_cart* $voucher->discount_value / 100;
            $total_cart_voucher = $price_voucher >= $data_cart ? 0 : $data_cart - $price_voucher;

            \Session::put('total_voucher_cart', $total_cart_voucher);

            if (!session()->has('voucher')) {
                \Session::put('voucher', $voucher);
            } else {
                return response()->json([
                    'message' => 'Voucher đã được áp dụng trước đó.',
                ], 403);
            }
        }
        \Session::save();
        return response()->json([
            'message' => 'Voucher đã được thêm thành công.',
        ], 200);
    }

    public function removeVoucher()
    {
        if (\Session::has('total_voucher_cart')) {
            \Session::forget('total_voucher_cart');
            \Session::forget('voucher');
            return response()->json([
                'message' => 'Voucher đã được xóa thành công.',
            ], 200);
        }

        return response()->json([
            'message' => 'Không có voucher nào để xóa.',
        ], 404);
    }


    public function deleteSS()
    {
        \Session::flush();
    }

    public function updateTotalPrice()
    {
        $total_cart = round(
            collect($this->cart)
                ->filter(function($item) {
                    return isset($item['cart_amount_sale']) && is_numeric($item['cart_amount_sale']);
                })
                ->sum('cart_amount_sale'), 
            2
        );
        \Session::put('total_cart', $total_cart);
        \Session::save();
    }
}
