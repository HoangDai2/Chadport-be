<?php

use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Admin\ProductControllerAD;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ColorController;
use App\Http\Controllers\Api\CommentController as ApiCommentController;
use App\Http\Controllers\Api\MomoController;
use App\Http\Controllers\Api\SizeController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VariantController;
use App\Http\Controllers\Api\VoucherController;
use App\Http\Controllers\Api\VoucherController_v2;
use App\Http\Controllers\Api\PaymentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductControllers;
use App\Http\Controllers\UserController as ControllersUserController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\NewAddressController;


// Group admin routes
Route::group(['prefix' => 'admin'], function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::group(['middleware' => ['auth:api', 'check.user.role']], function () { // role_id 1 2 3 
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/register', [AuthController::class, 'register']);


        Route::group(['prefix' => 'product'], function () {
            Route::get('/index', [ProductControllerAD::class, 'index']);
        });

        //oder 
        Route::group(['prefix' => 'order'], function () {
            Route::get('/index', [OrderController::class, 'index']);
            Route::get('/detail', [OrderController::class, 'detail']);
            Route::post('/status', [OrderController::class, 'edit']);
            Route::get('/delete', [OrderController::class, 'delete']);
        });
    });
});
Route::get('/list_refund', [OrderController::class, 'listRefund']);
Route::post('/confirm_refund', [OrderController::class, 'confirmRefund']);

// Group user routes
Route::group(['prefix' => 'user'], function () {
    Route::post('/register', [UserController::class, 'register']);
    Route::post('/login', [UserController::class, 'login']);
    Route::get('/getall', [UserController::class, 'GetAllUser']);
    Route::patch('/status/{id}', [UserController::class, 'toggleUserStatus']);
    Route::get('/profile', [UserController::class, 'getProfile'])->middleware(['jwt.cookie', 'auth:api']);
    Route::get('/activate-account/{user_id}/{token}', [UserController::class, 'activateAccount'])->name('activate-account');
    Route::group(['middleware' => ['api', 'auth:api']], function () {
        Route::post('/update', [UserController::class, 'update']);
        Route::post('/logout', [UserController::class, 'logout']);
        Route::post('/refresh', [UserController::class, 'refresh']);
        Route::get('/my_orders', [UserController::class, 'myOrders']);
        Route::post('/change_status_order', [UserController::class, 'changeMyOrder']);
        Route::post('/add_to_cart', [CartController::class, 'addToCart']);
        Route::post('/checkcart', [CartController::class, 'moveToCheckout']);
        Route::get('/getcheckcart', [CartController::class, 'Getcheckout']);
        Route::post('/updatecheckcart', [CartController::class, 'updatechecked']);
        Route::get('/cart', [CartController::class, 'get_cart']);
        Route::delete('/delete_product_cart', [CartController::class, 'deleteProductCart']);
        Route::post('/update_quatity_cart', [CartController::class, 'updateQuantityCart']);
        Route::post('/payment', [CartController::class, 'payment']);
        Route::post('/add-coupon-cart', [CartController::class, 'addCouponCart']);

        Route::post('/update_cart_quantity', [CartController::class, 'updateQuantity']);
        Route::post('/updateSizeColor', [CartController::class, 'updateSizeColor']);
        Route::post('/cancelOrder', [OrderController::class, 'cancelOrder']);

        Route::post('/buynows', [CartController::class, 'buyNow']);

        Route::post('/remove-voucher', [CartController::class, 'removeVoucher']);
        Route::post('/addadress', [NewAddressController::class, 'addAddress']);
        Route::get('/getadress', [NewAddressController::class, 'get_NewAddress']);
        // comments routes
        Route::post('add/comments', [ApiCommentController::class, 'addComments']);
        Route::get('getall/comments', [ApiCommentController::class, 'getCommentsByProduct']);
        Route::delete('delete/comments/{comment_id}', [ApiCommentController::class, 'deleteComment']);
        Route::post('update/comments/{comment_id}', [ApiCommentController::class, 'editCommentByUser']);
    });

    //Payment Vnpay
    Route::post('/create_paymentVnPay', [PaymentController::class, 'paymentVnPay']);
});
Route::get('commentsByProductId/{productId}', [ApiCommentController::class, 'showComments']);

// Product routes
Route::post('add/products', [ProductControllers::class, 'createProducts']);
Route::get('list/products', [ProductControllers::class, 'showProduct']);
Route::get('listall/products', [ProductControllers::class, 'listAll']);

Route::get('shop/products', [ProductControllers::class, 'showShopProducts']);
Route::get('showdetail/products/{id}', [ProductControllers::class, 'showDetail']);
Route::delete('delete/products/{id}', [ProductControllers::class, 'destroy']);
Route::post('restore/products/{id}', [ProductControllers::class, 'restoreProduct']);
Route::post('update/products/{id}', [ProductControllers::class, 'updateProduct']);
Route::get('/getDeletedProducts', [ProductControllers::class, 'getDeletedProducts']);
Route::get('/products/category/{category_id}', [ProductControllers::class, 'getProductsByCategory']);

// Category routes
Route::post('categories', [CategoryController::class, 'create'])->name('categories.creates');
Route::get('/categories/{id}', [CategoryController::class, 'show']);
Route::get('getall/categories', [CategoryController::class, 'GetAll'])->name('categories.GetAll');
Route::put('categories/{id}', [CategoryController::class, 'update'])->name('categories.updates');
Route::delete('categories/delete/{id}', [CategoryController::class, 'destroy'])->name('categories.destroy');
Route::post('/category/restore/{id}', [CategoryController::class, 'restoreCategory']);
Route::post('/category/forceDelete/{id}', [CategoryController::class, 'forceDelete']);
Route::get('/getDeletedCategories', [CategoryController::class, 'getDeletedCategories']);

// Color routes
Route::post('colors', [ColorController::class, 'createColor']); // 
Route::get('colors', [ColorController::class, 'GetAll']); // Lấy tất cả màu sắc
Route::get('colors/{id}', [ColorController::class, 'getColor']); // Lấy màu sắc theo ID
Route::put('colors/{id}', [ColorController::class, 'updates']); // Cập nhật màu sắc
Route::delete('colors/{id}', [ColorController::class, 'deleteColor']); // Xóa màu sắc

// Size routes
Route::get('/sizes', [SizeController::class, 'GetAll']);
Route::get('/sizes/{id}', [SizeController::class, 'show']);
Route::post('/sizes', [SizeController::class, 'creates']); // Đảm bảo có dòng này
Route::put('/sizes/{id}', [SizeController::class, 'updates']);
Route::delete('/sizes/{id}', [SizeController::class, 'destroy']);


// Variant routes
Route::post('products/creat', [VariantController::class, 'creates']); // Route để tạo mới variant
Route::get('productsvariants/{id}', [VariantController::class, 'show']);
Route::get('products/allvariants', [VariantController::class, 'GetAll']); // Route để lấy tất cả variants
Route::put('variants/{id}', [VariantController::class, 'updates']); // Route để cập nhật variant
Route::delete('variants/{id}', [VariantController::class, 'destroy']); // Route để xóa variant


// Brand route
Route::resource('brand', BrandController::class);

//Voucher route
Route::resource('voucher', VoucherController::class);

// start voucher hưng
Route::post('/add-voucher-to-user', [VoucherController_v2::class, 'assignToUser']);
Route::get('/get-vouchers-user/{id}', [VoucherController_v2::class, 'getUserVouchers']);
Route::get('/get-vouchers-user-client', [VoucherController_v2::class, 'getUserVouchersUser']);
Route::post('/gan-nhieu-voucher-to-user', [VoucherController_v2::class, 'assignToUsers']);
Route::post('/applyVoucher', [VoucherController_v2::class, 'applyVoucherToCart']);

// CRUD Voucher
Route::post('/vouchers', [VoucherController_v2::class, 'create']);
Route::get('/vouchers', [VoucherController_v2::class, 'index']);
Route::put('/vouchers/{id}', [VoucherController_v2::class, 'update']);
Route::get('/vouchers/{id}', [VoucherController_v2::class, 'show']);
Route::delete('/vouchers', [VoucherController_v2::class, 'delete']);
// // Gán voucher cho user
// end voucher hưng


// Momo payment
Route::post('/payment/create', [MomoController::class, 'createPayment']);
Route::get('/payment-return', [MomoController::class, 'paymentReturn']);

Route::get('/showAllOrder', [OrderController::class, 'showAllOrder']);
Route::get('/totalMoney', [OrderController::class, 'totalMoney']);

Route::get('/totalPr', [ProductControllers::class, 'totalProduct']);
Route::get('/totalUser', [ControllersUserController::class, 'totalUser']);



Route::get('/return_paymentVnPay', [PaymentController::class, 'returnPaymentVnPay']);

Route::post('/orders/bill/status', [OrderController::class, 'editBillStatus']);
Route::get('/all-ordersAdmin', [OrderController::class, 'getAllOrdersAdmin']);
Route::get('/all-ordersAdmin/{id}', [OrderController::class, 'getOrderById']);
Route::post('/updateOrdersPrintedStatus', [OrderController::class, 'updateOrdersPrintedStatus']);
Route::get('/user-status', [OrderController::class, 'getOrdersByUserAndStatus']);

Route::get('top-selling-products-by-month/{year}/{month}', [OrderController::class, 'getTopSellingProductsByMonth']);
Route::post('log-search', [ProductControllers::class, 'incrementSearchCount']);
Route::get('getTopSearchedProducts/{year}/{month}', [ProductControllers::class, 'getTopSearchedProducts']);
Route::get('getProductVariants/{cartItemId}', [CartController::class, 'getProductVariants']);

Route::get('/login/google', [UserController::class, 'redirectToGoogle']);
Route::post('/login/googlejwt', [UserController::class, 'googleLoginJWT']);
Route::get('/login/google/callback', [UserController::class, 'handleGoogleCallback']);

Route::post('/updateOrdersPrintedStatus', [OrderController::class, 'updateOrdersPrintedStatus']);