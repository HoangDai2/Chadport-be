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
use App\Models\Color;
use App\Models\Product;
use App\Models\Size;
use Illuminate\Support\Facades\Auth as FacadesAuth;
use Tymon\JWTAuth\Contracts\Providers\Auth;

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
                    'checked' => 0
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

            $cartItems = Cart_item::with('productItem.product') // Đảm bảo eager load product
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

            // Lấy thông tin chi tiết của từng sản phẩm trong giỏ hàng
            $formattedCartItems = $cartItems->map(function ($item) {
                $productDetails = $item->productItem->product;

                return [
                    'cart_item_ids' => $item->id,
                    'product_item_id' => $item->product_item_id,
                    'quantity' => $item->quantity,
                    'total_price' => $item->price,
                    'product_name' => $productDetails->name,  // Tên sản phẩm
                    'product_price' => $productDetails->price,  // Giá gốc
                    'product_sale_price' => $productDetails->price_sale,  // Giá giảm
                    'color' => $item->productItem->color,  // Màu sắc
                    'size' => $item->productItem->size,  // Kích thước
                    'image_product' => $productDetails->image_product,  // Đường dẫn ảnh sản phẩm
                ];
            });

            return response()->json([
                'message' => 'Danh sách các mục trong giỏ hàng.',
                'cart_id' => $cart->id,
                'cart_items' => $formattedCartItems,
                'total_price' => $totalPrice,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Có lỗi xảy ra.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // hàm này xử lí chuyển trạng thái true false của sản phẩm chọn hoặc bỏ chon
    public function moveToCheckout(Request $request)
    {
        try {
            $request->validate([
                'cart_item_ids' => 'required|array',  // Kiểm tra mảng các ID sản phẩm
                'cart_item_ids.*' => 'integer|exists:cart_item,id',  // Kiểm tra các ID có tồn tại trong bảng cart_items
            ]);
            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'Bạn cần đăng nhập để thực hiện thao tác này'], 401);
            }

            // Lấy giỏ hàng của người dùng
            $cart = Cart::where('user_id', $user->id)->first();
            if (!$cart) {
                return response()->json(['message' => 'Giỏ hàng không tồn tại'], 404);
            }

            // Cập nhật trạng thái "is_selected" cho các sản phẩm được chọn trong giỏ hàng
            $updatedItems = Cart_item::whereIn('id', $request->cart_item_ids)
                ->where('cart_id', $cart->id)
                ->update(['checked' => true]);  // Đánh dấu là đã được chọn
            
            // Lấy các sản phẩm đã được chọn
            $cartItems = Cart_item::where('cart_id', $cart->id)
                ->where('checked', true)
                ->get();

            if ($cartItems->isEmpty()) {
                return response()->json(['message' => 'Không có sản phẩm nào được chọn'], 400);
            }

            // Kiểm tra tồn kho và chuẩn bị di chuyển sản phẩm
            foreach ($cartItems as $cartItem) {
                $productItem = $cartItem->productItem;
                if ($productItem->quantity < $cartItem->quantity) {
                    return response()->json(['message' => 'Một số sản phẩm không đủ số lượng'], 400);
                }
            }

            // Di chuyển sản phẩm đã chọn sang checkout (thực hiện các hành động như lưu vào bảng checkout, v.v.)
            // Giả sử bạn có một bảng checkout hoặc hành động thêm sản phẩm vào bảng đó.
            
            return response()->json(['message' => 'Sản phẩm đã được chuyển sang checkout thành công']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Có lỗi xảy ra: ' . $e->getMessage()], 500);
        }
    }

    // hàm này xử lí sản phẩm được chọn từ bên giỏ hàng sang bên checkout
    public function Getcheckout(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Bạn cần đăng nhập để thanh toán'], 401);
        }
    
        $cart = Cart::where('user_id', $user->id)->first();
        if (!$cart) {
            return response()->json(['message' => 'Giỏ hàng không tồn tại'], 404);
        }
    
        // Lấy các sản phẩm đã được chọn (checked = true)
        $cartItems = Cart_item::where('cart_id', $cart->id)
                            ->where('checked', true)
                            ->get();
        
        // Tính tổng tiền thanh toán
        $totalAmount = $cartItems->sum('price'); // Tổng tiền thanh toán
    
        // Lấy dữ liệu chi tiết từng sản phẩm
        $responseItems = $cartItems->map(function($item) {
            $productDetails = $item->productItem->product;
    
            return [
                'cart_item_ids' => $item->id,
                'product_item_id' => $item->product_item_id,
                'quantity' => $item->quantity,
                'total_price' => $item->price,
                'product_name' => $productDetails->name,  // Tên sản phẩm
                'product_price' => $productDetails->price,  // Giá gốc
                'product_sale_price' => $productDetails->price_sale,  // Giá giảm
                'color' => $item->productItem->color,  // Màu sắc
                'size' => $item->productItem->size,  // Kích thước
                'image_product' => $productDetails->image_product,  // Đường dẫn ảnh sản phẩm
            ];
        });
    
        // Trả về dữ liệu
        return response()->json([
            'cart_items' => $responseItems,
            'total_amount' => $totalAmount
        ]);
    }
    
    // chuyển trạng thái từ true thành false bên checkout
    public function updatechecked(Request $request)
    {
        // Xác nhận người dùng đã đăng nhập
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Bạn cần đăng nhập để thực hiện thao tác này'], 401);
        }

        // Kiểm tra cart_item_id có hợp lệ hay không
        $request->validate([
            'cart_item_id' => 'required|integer|exists:cart_item,id', // Kiểm tra mỗi ID có tồn tại trong bảng cart_items
        ]);

        // Lấy giỏ hàng của người dùng
        $cart = Cart::where('user_id', $user->id)->first();
        if (!$cart) {
            return response()->json(['message' => 'Giỏ hàng không tồn tại'], 404);
        }

        // Cập nhật trạng thái checked thành false cho sản phẩm đã chọn
        $updatedItem = Cart_item::where('id', $request->cart_item_id)
                                ->where('cart_id', $cart->id)
                                ->update(['checked' => false]);

        // Kiểm tra xem có sản phẩm nào được cập nhật không
        if ($updatedItem === 0) {
            return response()->json(['message' => 'Sản phẩm không tồn tại trong giỏ hàng hoặc không có thay đổi'], 404);
        }

        return response()->json([
            'message' => 'Cập nhật trạng thái thành công',
            'updated_item_id' => $request->cart_item_id // Trả về ID sản phẩm đã được cập nhật
        ]);
    }



    public function deleteProductCart(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'Bạn cần đăng nhập để xóa sản phẩm khỏi giỏ hàng'], 401);
            }
    
            // Nếu có product_item_id, xóa sản phẩm cụ thể
            if ($request->has('product_item_id')) {
                $request->validate([
                    'product_item_id' => 'required|integer|exists:product_items,id',
                ]);
    
                $cart = Cart::where('user_id', $user->id)->first();
    
                if (!$cart) {
                    return response()->json(['message' => 'Giỏ hàng không tồn tại'], 404);
                }
    
                $cartItem = Cart_item::where('cart_id', $cart->id)
                    ->where('product_item_id', $request->product_item_id)
                    ->first();
    
                if (!$cartItem) {
                    return response()->json(['message' => 'Sản phẩm không có trong giỏ hàng'], 404);
                }
    
                $cartItem->delete();
    
                return response()->json(['message' => 'Sản phẩm đã được xóa khỏi giỏ hàng'], 200);
            }
    
            // Nếu không có product_item_id, xóa tất cả các sản phẩm trong giỏ hàng
            $cart = Cart::where('user_id', $user->id)->first();
    
            if (!$cart) {
                return response()->json(['message' => 'Giỏ hàng không tồn tại'], 404);
            }
    
            // Xóa tất cả các sản phẩm trong giỏ hàng
            Cart_item::where('cart_id', $cart->id)->delete();
    
            return response()->json(['message' => 'Tất cả sản phẩm đã được xóa khỏi giỏ hàng'], 200);
    
        } catch (\Exception $e) {
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

            // Lấy giỏ hàng của người dùng với trạng thái "đang sử dụng"
            $cart = Cart::where('user_id', $user_id)->where('status', 'đang sử dụng')->first();

            if (!$cart) {
                return response()->json(['message' => 'Giỏ hàng không hợp lệ.'], 400);
            }

            // Lọc các sản phẩm có trạng thái checked = 1
            $checkedItems = Cart_item::where('cart_id', $cart->id)
                                    ->where('checked', 1)
                                    ->get();

            if ($checkedItems->isEmpty()) {
                return response()->json(['message' => 'Không có sản phẩm nào được chọn để thanh toán.'], 400);
            }

            // Tính tổng tiền chỉ cho các sản phẩm đã được chọn
            $totalMoney = 0;
            $productIds = $checkedItems->pluck('product_item_id')->toArray();
            $productDataList = ProductItems::with('product')->whereIn('id', $productIds)->get()->keyBy('id');

            foreach ($checkedItems as $item) {
                $productData = $productDataList->get($item->product_item_id);
                if ($productData) {
                    // Sử dụng giá khuyến mãi nếu có, nếu không thì dùng giá gốc
                    $price = $productData->product->price_sale ?? $productData->product->price;
                    $totalMoney += $item->quantity * $price;
                }
            }

            // Cập nhật lại tổng tiền trong giỏ hàng (nếu cần)
            $cart->total = $totalMoney;
            $cart->save();

            // Tạo số đơn hàng
            $latestOrder = DB::table('order')->orderBy('id', 'desc')->first();
            $nextOrderNumber = $latestOrder ? intval(substr($latestOrder->oder_number, 6)) + 1 : 1;
            $orderNumber = 'order_' . $nextOrderNumber . '_' . $request->input('phone') . '_' . $request->input('email');

            // Tạo đơn hàng mới
            $order = DB::table('order')->insertGetId([
                'voucher_id' => $cart->voucher_id,
                'user_id' => $user_id,
                'payment_method' => $request->input('payment_method'),
                'total_money' => $totalMoney,
                'oder_number' => $orderNumber,
                'shipping_address' => $request->input('shipping_address'),
                'billing_address' => $request->input('billing_address'),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Lưu chi tiết đơn hàng và cập nhật số lượng sản phẩm
            foreach ($checkedItems as $item) {
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

                    // Cập nhật số lượng sản phẩm trong kho
                    $productData->quantity -= $quantity;
                    $productData->save();
                }
            }


            Cart_item::where('cart_id', $cart->id)->delete();
            $cart->delete();

            event(new OrderNotifications('Đơn hàng ' . $orderNumber . ' đã được đặt thành công!', $orderNumber, $user_id));

            Log::info('Event OrderNotifications đã được phát', ['orderNumber' => $orderNumber]);


            // Xóa các sản phẩm đã được chọn (checked = 1) trong giỏ hàng
            Cart_item::where('cart_id', $cart->id)
                    ->where('checked', 1)
                    ->delete();

            // Xóa các sản phẩm đã được chọn (is_buy_now = 1) trong giỏ hàng
            // Cart_item::where('cart_id', $cart->id)
            //         ->where('is_buy_now', 1)
            //         ->delete();

            // Nếu sau khi xóa các mục được chọn, giỏ hàng không còn mục nào, xóa giỏ hàng
            if (Cart_item::where('cart_id', $cart->id)->count() == 0) {
                $cart->delete();
            }

            // Phát sự kiện thông báo đơn hàng
            // event(new OrderNotifications('Đơn hàng ' . $orderNumber . ' đã được đặt thành công!', $orderNumber));
            // Log::info('Event OrderNotifications đã được phát', ['orderNumber' => $orderNumber]);

            DB::commit();

            return response()->json([
                'message' => 'Đơn hàng của bạn đã được đặt thành công',
                'order_id' => $order, // Trả về order_id
                'order_number' => $orderNumber, // Trả về số đơn hàng để sử dụng
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

    public function buyNow(Request $request)
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
    
            // Tìm hoặc tạo giỏ hàng cho người dùng
            $cart = Cart::firstOrCreate(['user_id' => $user->id]);
    
            DB::beginTransaction();
    
            // Kiểm tra xem sản phẩm đã có trong giỏ hàng chưa
            $cartItem = Cart_item::where('cart_id', $cart->id)
                ->where('product_item_id', $productItem->id)
                ->first();
    
            if ($cartItem) {
                // Nếu có thì cập nhật số lượng và giá trị price
                $newQuantity = $cartItem->quantity + $request->quantity;
    
                if ($newQuantity > $productItem->quantity) {
                    throw new \Exception('Số lượng sản phẩm đã đạt mức tối đa');
                }
    
                $cartItem->update([
                    'quantity' => $newQuantity,
                    'price' => $productItem->product->price_sale ?  $productItem->product->price_sale * $newQuantity :  $productItem->product->price * $newQuantity,
                    'checked' => 1 // Đánh dấu là đã được chọn khi cập nhật
                ]);
            } else {
                // Nếu chưa có thì tạo mới một Cart_item và đánh dấu checked = 1
                $data = [
                    'cart_id' => $cart->id,
                    'product_item_id' =>  $productItem->id,
                    'quantity' => $request->quantity,
                    'price' =>  $productItem->product->price_sale ?  $productItem->product->price_sale * $request->quantity :  $productItem->product->price * $request->quantity,
                    'checked' => 1 // Đánh dấu là đã được chọn ngay khi tạo mới
                ];
                Cart_item::create($data);
            }
    
            DB::commit();
    
            return response()->json([
                'message' => 'Thêm sản phẩm vào giỏ hàng thành công và đã được chọn.',
            ], 200);
    
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error adding to cart: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function getProductVariants($cartItemId)
    {
        // Lấy cart_item theo ID
        $cartItem = Cart_item::find($cartItemId);

        if ($cartItem) {
            // Lấy thông tin product_item thông qua quan hệ
            $productItem = $cartItem->productItem;

            // Lấy product_id
            $productId = $productItem->product_id;

            // Lấy tất cả các biến thể của sản phẩm, kèm thông tin size và color
            $variants = ProductItems::with(['size', 'color'])
                ->where('product_id', $productId)
                ->get();

            return $variants; // Trả về biến thể sản phẩm
        }

        return null; // Nếu không tìm thấy sản phẩm trong giỏ hàng
    }

    public function updateQuantity(Request $request)
    {
        // Validate dữ liệu nhận từ request
        $request->validate([
            'cart_item_ids' => 'required|exists:cart_item,id', // Kiểm tra tồn tại cart_item_ids
            'quantity' => 'required|integer|min:1', // Kiểm tra số lượng
        ]);

        // Tìm sản phẩm trong giỏ hàng theo cart_item_ids
        $cartItem = Cart_item::find($request->cart_item_ids);

        if (!$cartItem) {
            return response()->json([
                'message' => 'Sản phẩm không tồn tại trong giỏ hàng của bạn!',
            ], 404);
        }

        // Lấy thông tin biến thể (variant) của sản phẩm
        $variant = $cartItem->productItem; // Giả sử `productItem` là mối quan hệ tới bảng biến thể
        $variantQuantity = $variant->quantity; // Lấy số lượng biến thể còn lại

        // Kiểm tra xem số lượng trong giỏ hàng có lớn hơn số lượng còn lại của biến thể không
        if ($request->quantity > $variantQuantity) {
            return response()->json([
                'message' => 'Số lượng bạn yêu cầu vượt quá số lượng còn lại của biến thể này!',
            ], 400); // Trả về lỗi nếu vượt quá số lượng
        }

        // Nếu không vượt quá, cập nhật số lượng
        $cartItem->quantity = $request->quantity;
        $cartItem->save();

        return response()->json([
            'message' => 'Số lượng sản phẩm đã được cập nhật thành công!',
            'cart_item' => $cartItem, // Trả lại thông tin sản phẩm đã cập nhật
        ]);
    }

    public function updateSizeColor(Request $request)
    {
        // Validate dữ liệu đầu vào
        $request->validate([
            'cart_item_id' => 'required|exists:cart_item,id', // Kiểm tra cart_item tồn tại
            'color_id' => 'required|exists:colors,id',       // Kiểm tra color_id hợp lệ
            'size_id' => 'required|exists:sizes,id',         // Kiểm tra size_id hợp lệ
        ]);

        // Lấy thông tin sản phẩm trong giỏ hàng
        $cartItem = Cart_item::find($request->cart_item_id);

        if (!$cartItem) {
            return response()->json([
                'message' => 'Sản phẩm không tồn tại trong giỏ hàng!'
            ], 404);
        }

        // Tìm biến thể sản phẩm dựa vào color_id và size_id
        $productItem = ProductItems::where('product_id', $cartItem->productItem->product_id)
            ->where('color_id', $request->color_id)
            ->where('size_id', $request->size_id)
            ->first();

        if (!$productItem) {
            return response()->json([
                'message' => 'Biến thể không tồn tại!'
            ], 404);
        }

        // Cập nhật product_item_id trong bảng cart_item
        $cartItem->product_item_id = $productItem->id;
        $cartItem->save();

        return response()->json([
            'message' => 'Biến thể trong giỏ hàng đã được cập nhật thành công!',
            'cart_item' => $cartItem
        ]);
    }

}