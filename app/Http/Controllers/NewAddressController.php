<?php

namespace App\Http\Controllers;

use App\Models\NewAddress;
use App\Models\User;
use Illuminate\Http\Request;

class NewAddressController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            return $next($request);
        });
    }

    // Phương thức thêm địa chỉ mới
    public function addAddress(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone_number_address' => 'required|string|max:15',
            'address' => 'required|string|max:255',
            'specific_address' => 'nullable|string|max:255',
            'is_default' => 'boolean|nullable'
        ]);

        // Lấy thông tin người dùng đã đăng nhập
        $user = auth()->user();


        // Tạo địa chỉ mới và liên kết với người dùng
        $address = new NewAddress();
        $address->user_id = $user->id;
        $address->first_name = $request->input('first_name');
        $address->last_name = $request->input('last_name');
        $address->phone_number_address = $request->input('phone_number_address');
        $address->address = $request->input('address');
        $address->specific_address = $request->input('specific_address');
        $address->is_default = $request->input('is_default', false); // Mặc định là false nếu không có

        $address->save();

        return response()->json([
            'message' => 'Địa chỉ đã được thêm thành công',
            'address' => $address
        ]);
    }

    public function get_NewAddress ()
    {
        // Lấy thông tin người dùng hiện tại
        $user = auth()->user();
    
        // Lấy địa chỉ của người dùng
        $address = NewAddress::where('user_id', $user->id)->first();
    
        // Kiểm tra xem địa chỉ có tồn tại không
        if ($address) {
            return response()->json([
                'message' => 'Địa chỉ đã được tìm thấy',
                'address' => $address
            ], 200);
        } else {
            return response()->json([
                'message' => 'Không tìm thấy địa chỉ cho người dùng này'
            ], 404);
        }
    }
}
