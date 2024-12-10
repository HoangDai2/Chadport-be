<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Requests\Api\UpdateUserRequest;
use App\Mail\RegisterUserMail;
use App\Models\User;
use App\Traits\ImageUploadTrait;
use Exception;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class UserController extends Controller
{

    use ImageUploadTrait;

    public function __construct(Request $request)
    {
        $this->middleware('auth:api')->only(['logout', 'refresh']);
    }


    public function register(RegisterRequest $request)
    {
        try {
            $activationToken = Str::random(10);

            $userData = [
                'email' => $request->input('email'),
                'password' => bcrypt($request->input('password')),
                'firt_name' => $request->input('firt_name'),
                'last_name' => $request->input('last_name'),
                'role_id' => 4,
                'status' => "active"
            ];

            $user = User::create($userData);

            // $activationLink = route('activate-account', ['user_id' => $user->user_id, 'token' => $activationToken]);  // send main\l --> PT SMTP laravel | hhtps

            // Cache::put('activation_token_' . $user->id, $activationToken, now()->addDay());

            // Mail::to($user->email)->send(new RegisterUserMail($user, $activationLink));

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

    // function login này có xác minh nhưng chưa dùng tới 
    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        try {
            $user = User::where('email', $credentials['email'])->first();

            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }


            // Kiểm tra trạng thái tài khoản
            if ($user->status === 'inactive') {
                return response()->json(['error' => 'Your account is locked. Please contact support.'], 403); 
            }

            // Kiểm tra thông tin đăng nhập và tạo token
            JWTAuth::factory()->setTTL(60); // Đặt thời gian sống của token
            $token = JWTAuth::claims(['sub' => $user->id])->attempt($credentials);

            if (!$token) {
                return response()->json(['error' => 'Invalid Credentials'], 401);
            }
            // dd($token);
            // Trả về thông tin người dùng cùng token
            return response()->json([
                'message' => 'Successfully logged in',
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                    'status' => $user->status,
                    'firt_name' => $user->firt_name,
                    'last_name' => $user->last_name,
                    'gender' => $user->gender,
                    'birthday' => $user->birthday,
                    'address' => $user->address,
                    'image_user' => $user->image_user, // Trường ảnh người dùng
                    'phone_number' => $user->phone_number,
                ],
                'token' => $token
            ], 200)->cookie(
                'jwt_token',
                $token,
                60, // Thời gian sống của cookie
                '/',       // Đường dẫn của cookie
                null,      // Domain của cookie, có thể đặt thành null để dùng domain mặc định
                false,     // Đặt là false nếu bạn đang dùng HTTP (phát triển cục bộ)
                true       // HTTP-only
            );

        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Could not create token',
                'message' => $e->getMessage()
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Login error',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function activateAccount($user_id, $token)
    {
        $cachedToken = Cache::get('activation_token_' . $user_id);
    
        if (!$cachedToken || $cachedToken !== $token) {
            return response()->json(['error' => 'Invalid or expired activation link'], 403);
        }
    
        $user = User::find($user_id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
    
        $user->status = 1;
        $user->save();
    
        Cache::forget('activation_token_' . $user_id);
    
        return response()->json(['message' => 'Account activated successfully!'], 200);
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
            $token = JWTAuth::getToken();

    
            if (!$token) {
                return response()->json([
                    'error' => 'Token not provided',
                ], 400);
            }
    
            JWTAuth::setToken($token);
    
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

    public function update(UpdateUserRequest $request)
    {
        try {
            // Lấy người dùng hiện tại từ token xác thực
            $user = auth()->user();

            // Chuẩn bị dữ liệu cần cập nhật
            $updateData = [
             
                'gender' => $request->input('gender'),
                'birthday' => $request->input('birthday'),
                'address' => $request->input('address'),
            ];

            // Kiểm tra và cập nhật số điện thoại nếu người dùng nhập số mới
            if ($request->filled('phone_number') && $request->input('phone_number') !== $user->phone_number) {
                $updateData['phone_number'] = $request->input('phone_number');
            }

            // Kiểm tra và xử lý ảnh nếu có
            if ($request->hasFile('image_user')) {
                $data = $this->handleUploadImage($request, 'image_user', 'avt_image');
                if ($data) {
                    $updateData['image_user'] = $data;
                }
            }

            // Cập nhật dữ liệu một lần
            User::where('id', $user->id)->update($updateData);

            // Tải lại thông tin người dùng sau khi cập nhật
            $user = User::find($user->id);

            return response()->json(['message' => 'User information updated successfully', 'user' => $user], 200);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Could not update user information',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function getProfile()
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            return response()->json([
                'message' => 'User profile retrieved successfully',
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                    'status' => $user->status,
                    'firt_name' => $user->firt_name,
                    'last_name' => $user->last_name,
                    'gender' => $user->gender,
                    'birthday' => $user->birthday,
                    'address' => $user->address,
                    'image_user' => $user->image_user,
                    'phone_number' => $user->phone_number,
                ],
            ], 200);

        } catch (Exception $e) {
            return response()->json(['error' => 'Could not retrieve user profile', 'message' => $e->getMessage()], 500);
        }
    }

    public function GetAllUser()
    {
        try {
            // Lấy tất cả user từ cơ sở dữ liệu
            $users = User::all();

            return response()->json([
                'message' => 'User list retrieved successfully',
                'users' => $users
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Could not retrieve user list',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function toggleUserStatus($id)
    {
        try {
            // Tìm người dùng theo ID
            $user = User::findOrFail($id);

            // Kiểm tra nếu trạng thái hiện tại là "active", thì đổi thành "inactive" (khóa tài khoản)
            // Nếu trạng thái là "inactive", đổi thành "active" (mở khóa tài khoản)
            $user->status = $user->status === 'active' ? 'inactive' : 'active';
            $user->save(); // Lưu thay đổi vào cơ sở dữ liệu

            // Trả về thông báo thành công
            return response()->json([
                'message' => 'User status updated successfully',
                'user' => $user
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'User not found',
                'message' => 'No user found with the given ID'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Could not update user status',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    

}
