<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart_item;
use App\Models\Voucher;
use Database\Seeders\Voucher_usedSeeder;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Cart;
use App\Models\ProductItems;
use App\Events\OrderNotifications;

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
            $request->validate([
                'product_item_id' => 'required|integer|exists:product_items,id',
                'quantity' => 'required|integer|min:1',
            ]);
    
            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'Bạn cần đăng nhập để thêm sản phẩm vào giỏ hàng'], 401);
            }
    
            $productItem = ProductItems::with('product')->findOrFail($request->product_item_id);

            if ($productItem->quantity < $request->quantity) {
                return response()->json(['message' => 'Sản phẩm không đủ số lượng'], 400);
            }
    
            $cart = Cart::firstOrCreate(['user_id' => $user->id]);
    
            DB::beginTransaction();
            
            $cartItem = Cart_item::where('cart_id', $cart->id)
                ->where('product_item_id', $productItem->id)
                ->first();

            if ($cartItem) {
                $newQuantity = $cartItem->quantity + $request->quantity;

                if ($newQuantity > $productItem->quantity) {
                    throw new \Exception('Số lượng sản phẩm đã đạt mức tối đa');
                }

                $cartItem->update([
                    'quantity' => $newQuantity,
                    'price' => $productItem->product->price_sale ?  $productItem->product->price_sale * $newQuantity :  $productItem->product->price * $newQuantity,
                ]);
            } else {
                $data = [
                    'cart_id' => $cart->id,
                    'product_item_id' =>  $productItem->id,
                    'quantity' => $request->quantity,
                    'price' =>  $productItem->product->price_sale ?  $productItem->product->price_sale * $request->quantity :  $productItem->product->price * $request->quantity ,
                ];
                Cart_item::create($data);
            }
            DB::commit();

            return response()->json([
                'message' => 'Thêm sản phẩm vào giỏ hàng thành công',
            ], 200);
    
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error adding to cart: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function get_cart()
    {
        try {
            $cart = Cart::where('user_id', auth()->id())
                ->where('status', 'đang sử dụng')
                ->first();
    
            if (!$cart) {
                return response()->json([
                    'message' => 'Giỏ hàng trống.',
                    'cart_items' => [],
                    'total_price' => 0,
                ], 200);
            }
    
            $cartItems = Cart_item::with('productItem')
                ->where('cart_id', $cart->id)
                ->get();
    
            if ($cartItems->isEmpty()) {
                return response()->json([
                    'message' => 'Giỏ hàng trống.',
                    'cart_items' => [],
                    'total_price' => 0,
                ], 200);
            }
    
            $totalPrice = $cartItems->sum(function ($item) {
                return $item->price;
            });
    
            $formattedCartItems = $cartItems->map(function ($item) {
                return [
                    'product_item_id' => $item->product_item_id,
                    'quantity' => $item->quantity,
                    'total_price' => $item->price,
                ];
            });

            $cart->total = $totalPrice;
            $cart->save();
    
            return response()->json([
                'message' => 'Danh sách các mục trong giỏ hàng.',
                'cart_id' => $cart->id,
                'cart_items' => $formattedCartItems,
                'total_price' => $cart->total,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving cart: ' . $e->getMessage());
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi lấy thông tin giỏ hàng.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteProductCart(Request $request)
    {
        try {
            $cartItem = Cart_item::whereHas('cart', function ($query) use ($request) {
                $query->where('user_id', auth()->id())
                      ->where('status', 'đang sử dụng');
            })
            ->where('product_item_id', $request->product_item_id)
            ->first();

            if (!$cartItem) {
                return response()->json([
                    'message' => 'Giỏ hàng trống hoặc sản phẩm không có trong giỏ hàng.',
                ], 404);
            }

            $cartItem->delete();
            return $this->get_cart();
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function updateQuantityCart(Request $request)
    {
        $cart = Cart::where('user_id', auth()->id())
        ->where('status', 'đang sử dụng')
        ->first();

        if (!$cart) {
            return response()->json(['message' => 'Giỏ hàng không tồn tại.'], 404);
        }

        $cartItem = Cart_item::where('cart_id', $cart->id)
            ->where('product_item_id', $request->product_item_id)
            ->first();

        if (!$cartItem) {
            return response()->json(['message' => 'Sản phẩm không có trong giỏ hàng.'], 404);
        }

        if ($request->option === 'down') {
            if ($cartItem->quantity === 1) {
                $cartItem->delete();
                return response()->json(['message' => 'Sản phẩm đã được xóa khỏi giỏ hàng.']);
            }
            $cartItem->decrement('quantity');
        } elseif ($request->option === 'up') {
            $cartItem->increment('quantity');
        }
    
        $productItem = ProductItems::with('product')->findOrFail($request->product_item_id);
        $cartItem->price = $productItem->product->price_sale 
            ? $productItem->product->price_sale * $cartItem->quantity 
            : $productItem->product->price * $cartItem->quantity;
    
        $cartItem->save();

        return $this->get_cart();
    }

    public function payment(Request $request)
    {
        DB::beginTransaction();
        try {
            $user_id = auth()->id();
    
            $cart = Cart::where('user_id', $user_id)->where('status', 'đang sử dụng')->first();
    
            if (!$cart) {
                return response()->json(['message' => 'Giỏ hàng không hợp lệ.'], 400);
            }
    
            $data_cart_items = Cart_item::where('cart_id', $cart->id)->get();

            $latestOrder = DB::table('order')->orderBy('id', 'desc')->first();
            $nextOrderNumber = $latestOrder ? intval(substr($latestOrder->oder_number, 6)) + 1 : 1;
            $orderNumber = 'order_' . $nextOrderNumber . '_' . $request->input('phone') . '_' . $request->input('email');
            
            $order = DB::table('order')->insertGetId([
                'voucher_id' => $cart->voucher_id,
                'user_id' => $user_id,
                'payment_method' => $request->input('payment_method'),
                'total_money' => $cart->total,
                'oder_number' => $orderNumber,
                'shipping_address' => $request->input('shipping_address'),
                'billing_address' => $request->input('billing_address'),
                'created_at' => now(),
                'updated_at' => now()
            ]);
    
            $productIds = $data_cart_items->pluck('product_item_id')->toArray();
            $productDataList = ProductItems::with('product')->whereIn('id', $productIds)->get()->keyBy('id');

            foreach ($data_cart_items as $item) {
                $productData = $productDataList->get($item->product_item_id);
                $quantity = intval($item->quantity);
            
                if ($productData) {
                    $price = $productData->product->price_sale ?? $productData->product->price;
                    $total_price = $quantity * $price;
            
                    DB::table('order_detail')->insert([
                        'order_id' => $order,
                        'product_item_id' => $item->product_item_id,
                        'quantity' => $quantity,
                        'price' => $price,
                        'total_price' => $total_price,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
            
                    // Cập nhật số lượng sản phẩm
                    $productData->quantity -= $quantity;
                    $productData->save();
                }
            }

            Cart_item::where('cart_id', $cart->id)->delete();
            $cart->delete();

            event(new OrderNotifications('Đơn hàng ' . $orderNumber . ' đã được đặt thành công!', $orderNumber, $user_id));

            Log::info('Event OrderNotifications đã được phát', ['orderNumber' => $orderNumber]);


            DB::commit();
    
            return response()->json(['message' => 'Đơn hàng của bạn đã được đặt thành công'], 200);
    
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
        $request->validate(['voucher_id' => 'required|integer|exists:vouchers,id']);
    
        $userId = auth()->id();
        $voucher = Voucher::find($request->voucher_id);
    
        if (!$voucher) {
            return response()->json(['message' => 'Không tìm thấy voucher.'], 404);
        }
    
        // if (DB::table('voucher_used')->where('voucher_id', $request->voucher_id)->where('user_id', $userId)->exists()) {
        //     return response()->json(['message' => 'Bạn đã sử dụng voucher này rồi.'], 403);
        // }
    
        $cart = Cart::where('user_id', $userId)->where('status', 'đang sử dụng')->first();
    
        if (!$cart) {
            return response()->json(['message' => 'Giỏ hàng không hợp lệ.'], 400);
        }
    
        $data_cart = $cart->total;
        $total_cart_voucher = $data_cart;
    
        if ($voucher->discount_type === 'percentage') {
            $price_voucher = $data_cart * $voucher->discount_value / 100;
            $total_cart_voucher = max($data_cart - $price_voucher, 0);
        } elseif ($voucher->discount_type === 'fixed_amount') {
            $total_cart_voucher = max($data_cart - $voucher->discount_value, 0);
        }
    
        $cart->total = $total_cart_voucher;
        $cart->save();
    
        // DB::table('voucher_used')->updateOrInsert(
        //     ['voucher_id' => $request->voucher_id, 'user_id' => $userId],
        //     ['created_at' => now()]
        // );
    
        return response()->json(['message' => 'Voucher đã được thêm thành công.', 'total_cart' => $total_cart_voucher], 200);
    }

    public function removeVoucher(Request $request)
    {
        $userId = auth()->id();
        $cart = Cart::where('user_id', $userId)->where('status', 'đang sử dụng')->first();

        if (!$cart) {
            return response()->json(['message' => 'Giỏ hàng không hợp lệ.'], 400);
        }

        $voucher = DB::table('voucher_used')->where('user_id', $userId)->first();

        if (!$voucher) {
            return response()->json(['message' => 'Không có voucher nào để xóa.'], 404);
        }

        $data_cart = $cart->total;

        if ($voucher->discount_type === 'percentage') {
            $price_voucher = $data_cart * $voucher->discount_value / (100 - $voucher->discount_value);
            $cart->total = $data_cart + $price_voucher;
        } elseif ($voucher->discount_type === 'fixed_amount') {
            $cart->total = $data_cart + $voucher->discount_value;
        }

        $cart->save();
        DB::table('voucher_used')->where('user_id', $userId)->delete();

        return response()->json(['message' => 'Voucher đã được xóa thành công.'], 200);
    }
}