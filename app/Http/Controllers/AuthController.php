<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RegisterRequest;
use App\Http\Requests\Admin\LoginRequest;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __construct() {
        $this->middleware('auth:api', ['except' => ['login']]);
    }
// chỉ supper Admin (role_id:1) thì dùng đc hàm này
    public function register(RegisterRequest $request)
    {
        try {
            $userData = [
                'email' => $request->input('email'),
                'password' => bcrypt($request->input('password')),
                'role_id' => $request->input('role_id'),
                'status' =>'active'
            ];

            $user = User::create($userData);

            return response()->json([
                'message' => 'Successfully created user',
                'user' => $user
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Could not register user',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function login(LoginRequest $request)
{
    $credentials = $request->only('email', 'password');

    try {
        // Tìm kiếm người dùng theo email
        $user = User::where('email', $credentials['email'])->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Kiểm tra trạng thái tài khoản
        if ($user->status === 'inactive') {
            return response()->json(['error' => 'Your account is locked. Please contact support.'], 403);
        }

        // Kiểm tra role_id của người dùng, chỉ cho phép đăng nhập nếu role_id là 1 hoặc 2
        if (!in_array($user->role_id, [1, 2])) {
            return response()->json(['error' => 'You do not have permission to access this area.'], 403);
        }

        // Đặt thời gian sống cho token (60 phút)
        JWTAuth::factory()->setTTL(60);

        // Thêm thông tin `sub` vào token và kiểm tra thông tin đăng nhập
        $token = JWTAuth::claims(['sub' => $user->id])->attempt($credentials);

        if (!$token) {
            return response()->json(['error' => 'Invalid Credentials'], 401);
        }

        // Trả về thông tin người dùng và token
        return response()->json([
            'message' => 'Successfully logged in',
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'status' => $user->status,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'gender' => $user->gender,
                'birthday' => $user->birthday,
                'address' => $user->address,
                'image_user' => $user->image_user,
                'phone_number' => $user->phone_number,
            ],
            'token' => $token
        ], 200)->cookie(
            'jwt_token',
            $token,
            60,    // Cookie sống 60 phút
            '/',   // Áp dụng cookie cho toàn bộ domain
            null,  // Domain mặc định
            false, // Không sử dụng HTTPS trong môi trường phát triển
            true   // HTTP-only
        );

    } catch (JWTException $e) {
        // Xử lý lỗi JWT
        return response()->json([
            'error' => 'Could not create token',
            'message' => $e->getMessage()
        ], 500);
    } catch (Exception $e) {
        // Xử lý các lỗi khác
        return response()->json([
            'error' => 'Login error',
            'message' => $e->getMessage()
        ], 500);
    }
}


    public function logout(Request $request)
    {
        try {
            $request->user()->tokens()->delete();

            return response()->json([
                'message' => 'Successfully logged out',
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Could not log out',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function refresh()
    {
        try {
            $token = auth()->getToken();
    
            if (!$token) {
                return response()->json([
                    'error' => 'Token not provided',
                ], 400);
            }
    
            JWTAuth::setToken($token);  // khởi tạo phiên token trong jwt --> để jwwt biết phiên token này đã đã hoạt động 
    
            // Refresh token
            $newToken = JWTAuth::refresh($token);
    
            return response()->json([
                'message' => 'Token refreshed successfully',
                'token' => $newToken
            ], 200);
    
        } catch (TokenExpiredException $e) {
            return response()->json([
                'error' => 'Token has expired',
                'message' => $e->getMessage()
            ], 401);
    
        } catch (TokenInvalidException $e) {
            return response()->json([
                'error' => 'Token is invalid',
                'message' => $e->getMessage()
            ], 401);
    
        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Could not refresh token',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    // Hàm thay đổi role_id của user dành cho supper Admin
    public function changeUserRole(Request $request) {
        $user = JWTAuth::parseToken()->authenticate();
        if ($user->role_id == 1) {
            $userId = $request->input('id');
            $newRoleId = $request->input('role_id');
            if ($newRoleId > 1) {
                $userToUpdate = User::find($userId);
                if($userToUpdate) {
                $userToUpdate->role_id = $newRoleId;
                $userToUpdate->save();
                return response()->json([
                    'message' => 'Cập nhât role_id thành công',
                    'user' => $userToUpdate
                ], 200);
                } else {
                return response()->json([
                    'error' => 'Không có người dùng với id này'
                ], 404);
                }
            } else {
                return response()->json([
                    'error' => 'Không thể cập nhật role_id nhỏ hơn 1'
                ], 400);
            }
            
        } else {
            return response()->json([
                'error' => 'Bạn không có quyền truy cập'
            ], 403);
        }
    }

    public function activateAccount(Request $request) {
        $user = JWTAuth::parseToken()->authenticate();
        $userId = $request->input('id');
        $newStatus = $request->input('status');
        $userToUpdate = User::find($userId);
        if(!$userToUpdate){
            return response()->json([
                'error' => 'Không có người dùng với id này'
            ], 404);
        }
        if ($user->role_id == 1) {
            
            if ($userToUpdate->role_id != 1) {
                $userToUpdate->status = $newStatus;
                $userToUpdate->save();
                return response()->json(['message' => 'Cập nhật trạng thái user thành công!!'], 200);
            } else {
                return response()->json(['error' => 'Quản trị viên không thể thay đổi trạng thái của quản trị viên khác !!'], 403);
            }
        } elseif ($user->role_id == 0) {
            
            $userToUpdate->status = $newStatus;
            $userToUpdate->save();
            return response()->json(['message' => 'Cập nhật trạng thái user thành công!!'], 200);
        } else {
            return response()->json(['error' => 'Không được phép'], 403);
        }

    }
}