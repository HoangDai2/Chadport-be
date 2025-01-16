<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\VoucherResource;
use App\Models\Voucher;
use Illuminate\Http\Request;
use App\Models\VoucherDetails;
use App\Models\Cart_item;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;



class VoucherController_v2 extends Controller
{
    public function assignToUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'voucher_id' => 'required|exists:vouchers,id',
        ]);

        // Lấy thông tin voucher
        $voucher = Voucher::findOrFail($request->voucher_id);

        // Kiểm tra nếu voucher đã hết lượt sử dụng
        if ($voucher->usage_limit <= $voucher->used_count) {
            return response()->json([
                'message' => 'Voucher này đã đạt giới hạn sử dụng và không thể gán thêm.',
            ], 400);
        }

        // Kiểm tra nếu voucher đã được gán cho user
        $existingAssignment = VoucherDetails::where('user_id', $request->user_id)
            ->where('voucher_id', $request->voucher_id)
            ->exists();

        if ($existingAssignment) {
            return response()->json([
                'message' => 'Voucher đã được gán cho người dùng này trước đó.',
            ], 400);
        }

        // Gán voucher cho user
        VoucherDetails::create([
            'user_id' => $request->user_id,
            'voucher_id' => $request->voucher_id,
        ]);
        $voucher->update(['is_default' => 2]);
        return response()->json(['message' => 'Voucher assigned to user successfully'], 201);
    }

    public function getUserVouchers($userId)
    {
        // Lấy danh sách voucher của user
        $vouchers = VoucherDetails::where('user_id', $userId)
            ->with('voucher')
            ->get();

        return response()->json(['vouchers' => $vouchers]);
    }
    public function     getUserVouchersUser(Request $request)
    {
        try {
            // Lấy thông tin user từ token
            $user = auth()->user();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized. Please log in.'], 401);
            }

            // Lấy danh sách voucher của user
            $vouchers = VoucherDetails::where('user_id', $user->id)
                ->with('voucher') // Gắn quan hệ để lấy thông tin voucher
                ->get();

            return response()->json([
                'message' => 'Successfully fetched user vouchers.',
                'vouchers' => $vouchers,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch vouchers.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Tạo voucher
    public function create(Request $request)
    {
        // Validate dữ liệu đầu vào
        $request->validate([
            'code' => 'required|unique:vouchers,code|max:50', // Mã voucher phải duy nhất
            'discount_type' => 'required|in:fixed,percentage', // Loại giảm giá
            'discount_value' => [
                'required',
                'numeric',
                'min:1',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->discount_type === 'percentage' && $value > 100) {
                        $fail('Discount value cannot exceed 100% for percentage discounts.');
                    }
                },
            ],
            'expires_at' => 'required|date', // Ngày hết hạn
            'usage_limit' => 'required|integer|min:1', // Giới hạn số lần sử dụng
        ], [
            'code.unique' => 'Voucher code already exists.',
            'discount_value.min' => 'Discount value must be greater than 0.',
        ]);

        // Tạo voucher mới
        $voucher = Voucher::create([
            'code' => $request->code,
            'discount_type' => $request->discount_type,
            'discount_value' => $request->discount_value,
            'expires_at' => $request->expires_at,
            'usage_limit' => $request->usage_limit,
            'used_count' => 0, // Ban đầu số lần sử dụng là 0
            'is_client' => "an",
            'is_default' => 0,
        ]);

        // Trả về phản hồi thành công
        return response()->json([
            'message' => 'Voucher created successfully',
            'voucher' => $voucher
        ], 201);
    }

    // crud start
    public function index()
    {
        $vouchers = Voucher::all();
        return response()->json($vouchers);
    }

    // Lấy chi tiết một voucher
    public function show($id)
    {
        $voucher = Voucher::findOrFail($id);
        return response()->json($voucher);
    }

    // Cập nhật voucher
    public function update(Request $request, $id)
    {
        $voucher = Voucher::findOrFail($id);

        $request->validate([
            'code' => 'unique:vouchers,code,' . $voucher->id,
            'discount_type' => 'in:fixed,percentage',
            'discount_value' => 'numeric|min:0',
            'expires_at' => 'date',
            'usage_limit' => 'integer|min:1',
        ]);

        $voucher->update($request->all());
        return response()->json(['message' => 'Voucher updated successfully', 'voucher' => $voucher]);
    }
    public function updateIsClient($id)
    {
        // Tìm voucher theo ID, nếu không tìm thấy sẽ trả lỗi 404
        $voucher = Voucher::findOrFail($id);

        // Cập nhật giá trị is_client thành 'hiện'
        $voucher->is_client = 'hien'; // Đặt giá trị mới cho is_client
        $voucher->save(); // Lưu lại thay đổi vào cơ sở dữ liệu

        // Trả về phản hồi JSON
        return response()->json([
            'message' => 'Voucher updated successfully',
            'voucher' => $voucher
        ], 200);
    }

    public function delete(Request $request)
    {
        // Kiểm tra dữ liệu đầu vào
        $request->validate([
            'id' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (is_array($value)) {
                        if (count($value) < 2) {
                            $fail('If id is an array, it must contain at least 2 elements.');
                        }
                    } elseif (!is_numeric($value)) {
                        $fail('If id is not an array, it must be a numeric value.');
                    }
                }
            ],
            'id.*' => 'integer|exists:vouchers,id', // Nếu là mảng, từng phần tử phải là số nguyên và tồn tại
        ]);

        // Xử lý logic xóa
        if (is_array($request->id)) {
            // Xóa nhiều ID
            $ids = $request->id;
        } else {
            // Xóa một ID
            $ids = [$request->id];
        }

        // Thực hiện xóa
        $deleted = Voucher::whereIn('id', $ids)->delete();

        // Trả về phản hồi
        if ($deleted) {
            return response()->json([
                'message' => 'Voucher(s) deleted successfully.',
                'deleted_count' => $deleted
            ]);
        }

        return response()->json(['error' => 'No vouchers were deleted.'], 400);
    }

    // get voucher bên client chỉ hiện voucher chưa hết hạn
    public function clientVouchers()
    {
        // Chỉ trả về các voucher chưa hết hạn
        $vouchers = Voucher::where('expires_at', '>', now())->get();
        return response()->json($vouchers);
    }
    

    // end  crud


    // Gán voucher cho nhiều user
    public function assignToUsers(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'voucher_id' => 'required|exists:vouchers,id',
        ]);

        $voucherId = $request->voucher_id;
        $voucher = Voucher::findOrFail($voucherId); // Lấy thông tin voucher

        // Kiểm tra nếu voucher đã đạt giới hạn sử dụng
        if ($voucher->usage_limit <= $voucher->used_count) {
            return response()->json([
                'message' => 'Voucher này đã đạt giới hạn sử dụng và không thể gán thêm.',
            ], 400);
        }

        $assignedUsers = []; // Danh sách user được gán voucher thành công
        $skippedUsers = []; // Danh sách user đã có voucher trước đó

        foreach ($request->user_ids as $userId) {
            // Kiểm tra nếu voucher đã được gán cho user
            $exists = VoucherDetails::where('user_id', $userId)
                ->where('voucher_id', $voucherId)
                ->exists();

            if ($exists) {
                $skippedUsers[] = $userId; // Bỏ qua user nếu đã được gán voucher
                continue;
            }

            // Kiểm tra nếu đã vượt quá usage_limit
            if ($voucher->usage_limit <= $voucher->used_count + count($assignedUsers)) {
                return response()->json([
                    'message' => 'Voucher đã đạt giới hạn sử dụng và không thể gán thêm.',
                    'assigned_users' => $assignedUsers,
                    'skipped_users' => $skippedUsers,
                ], 400);
            }

            // Thêm voucher mới cho user
            try {
                VoucherDetails::create([
                    'user_id' => $userId,
                    'voucher_id' => $voucherId,
                ]);

                $assignedUsers[] = $userId; // Ghi lại user được gán thành công
            } catch (QueryException $e) {
                // Nếu có lỗi khác xảy ra
                return response()->json(['error' => 'Đã xảy ra lỗi khi gán voucher cho user: ' . $userId], 500);
            }
        }

        // Cập nhật số lần sử dụng voucher
        $voucher->increment('used_count', count($assignedUsers));

        return response()->json([
            'message' => 'Voucher processing completed',
            'assigned_users' => $assignedUsers, // Người dùng được gán voucher thành công
            'skipped_users' => $skippedUsers, // Người dùng đã bị bỏ qua
        ]);
    }

    public function applyVoucherToCart(Request $request)
    {
        $user = auth()->user();

        // Kiểm tra nếu người dùng chưa đăng nhập
        if (!$user) {
            return response()->json(['error' => 'Bạn cần đăng nhập để áp dụng mã giảm giá.'], 401);
        }

        // Kiểm tra quyền (role_id phải bằng 3)
        if ($user->role_id != 3) {
            return response()->json(['error' => 'Bạn không có quyền áp dụng mã giảm giá.'], 403);
        }

        $request->validate([
            'cart_id' => 'required|exists:cart_item,cart_id',
            'voucher_code' => 'required|exists:vouchers,code',
        ]);

        // Lấy mã giảm giá
        $voucher = Voucher::where('code', $request->voucher_code)
            ->where('expires_at', '>', now())
            ->whereColumn('used_count', '<', 'usage_limit')
            ->first();

        if (!$voucher) {
            return response()->json(['error' => 'Voucher không hợp lệ hoặc đã hết hạn.'], 400);
        }

        // Lấy các sản phẩm trong giỏ hàng có trạng thái checked = 1
        $cartItems = Cart_item::where('cart_id', $request->cart_id)
            ->where('checked', 1)
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['error' => 'Không có sản phẩm nào được chọn để áp dụng mã giảm giá.'], 400);
        }

        // Tính tổng tiền trước giảm giá (originalTotal)
        $originalTotal = $cartItems->sum(function ($item) {
            return $item->price * $item->quantity;
        });

        // Tính tổng giảm giá
        $discountValue = 0;
        if ($voucher->discount_type === 'fixed') {
            $discountValue = min($voucher->discount_value, $originalTotal);
        } elseif ($voucher->discount_type === 'percentage') {
            $discountValue = $originalTotal * ($voucher->discount_value / 100);
        }

        // Tính tổng tiền sau giảm giá
        $totalAfterDiscount = max(0, $originalTotal - $discountValue);

        // Cập nhật voucher_id cho các sản phẩm trong giỏ hàng
        foreach ($cartItems as $item) {
            $item->voucher_id = $voucher->id;
            $item->save();
        }

        // Phản hồi thông tin
        return response()->json([
            'message' => 'Voucher đã được áp dụng thành công.',
            'voucher_id' => $voucher->id,
            'discount_value' => $voucher->discount_value,
            'discount_type' => $voucher->discount_type,
            'cart_items' => $cartItems,
            'original_total' => $originalTotal, // Tổng tiền trước giảm giá
            'total_discounted_amount' => $totalAfterDiscount // Tổng tiền sau giảm giá
        ]);
    }


    public function claimVoucher(Request $request)
    {
        $user = auth()->user();

        // Kiểm tra nếu người dùng chưa đăng nhập
        if (!$user) {
            return response()->json(['message' => 'Bạn cần đăng nhập để nhận voucher.'], 401);
        }

        $request->validate([
            'voucher_id' => 'required|exists:vouchers,id',
        ]);

        // Lấy thông tin voucher
        $voucher = Voucher::where('id', $request->voucher_id)
            ->where('is_default', 3) // Chỉ cho phép claim voucher không phải mặc định
            ->first();

        if (!$voucher) {
            return response()->json([
                'message' => 'Voucher này không hợp lệ hoặc không thể được claim.',
            ], 400);
        }

        // Kiểm tra nếu voucher đã hết hạn
        if (now()->greaterThan($voucher->expires_at)) {
            return response()->json([
                'message' => 'Voucher này đã hết hạn.',
            ], 400);
        }

        // Kiểm tra nếu voucher đã đạt giới hạn sử dụng
        if ($voucher->usage_limit <= $voucher->used_count) {
            return response()->json([
                'message' => 'Voucher này đã đạt giới hạn sử dụng và không thể nhận thêm.',
            ], 400);
        }

        // Kiểm tra nếu user đã nhận voucher này trước đó
        $existingClaim = VoucherDetails::where('user_id', $user->id)
            ->where('voucher_id', $voucher->id)
            ->exists();

        if ($existingClaim) {
            return response()->json([
                'message' => 'Bạn đã nhận voucher này trước đó.',
            ], 400);
        }

        // Gán voucher cho user
        VoucherDetails::create([
            'user_id' => $user->id,
            'voucher_id' => $voucher->id,
        ]);

        return response()->json([
            'message' => 'Bạn đã nhận voucher thành công.',
            'voucher' => $voucher,
        ], 201);
    }

    public function updateRoleClient3(Request $request)
{
    // Validate dữ liệu từ request
    $validatedData = $request->validate([
        'voucher_id' => 'required|exists:vouchers,id', // Xác nhận voucher tồn tại
        'is_default' => 'required|in:0,3', // Chỉ cho phép giá trị 0 hoặc 3
    ]);

    try {
        // Lấy thông tin voucher từ DB
        $voucher = Voucher::findOrFail($validatedData['voucher_id']);

        // Kiểm tra ngày hết hạn của voucher
        $today = now(); // Lấy thời gian hiện tại
        if ($voucher->expires_at < $today) {
            // Nếu voucher đã hết hạn, tự động cập nhật is_default về 0
            $voucher->update(['is_default' => 0]);

            return response()->json([
                'message' => 'Voucher expired, is_default updated to 0',
                'voucher_id' => $voucher->id,
                'is_default' => $voucher->is_default,
            ], 200);
        }

        // Nếu chưa hết hạn, cập nhật trạng thái theo giá trị từ FE
        $voucher->update(['is_default' => $validatedData['is_default']]);

        return response()->json([
            'message' => 'Voucher updated successfully',
            'voucher_id' => $voucher->id,
            'is_default' => $voucher->is_default,
        ], 200);
    } catch (\Exception $e) {
        // Bắt lỗi và trả về thông báo lỗi
        // \Log::error("Error updating voucher: " . $e->getMessage());

        return response()->json([
            'message' => 'Error updating voucher',
            'error' => $e->getMessage(),
        ], 500);
    }
}


    

}
