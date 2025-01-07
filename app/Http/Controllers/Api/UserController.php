<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Requests\Api\UpdateUserRequest;
use App\Mail\NotiDestroyMail;
use App\Mail\RegisterUserMail;
use App\Models\Order;
use App\Models\User;
use App\Traits\ImageUploadTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Google\Client;
use Laravel\Socialite\Facades\Socialite;

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
                'role_id' => 3,
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


    public function myOrders()
    {
        try{
            $data = Order::with(['user', 'voucher', 'orderDetails.productItem.product', 
                'orderDetails.productItem.color', 
                'orderDetails.productItem.size'])
                ->where('user_id', Auth::user()->id)
                ->get();

                return response()->json([
                    'message' => 'Danh sách đơn hàng của bạn',
                    'data' => $data
                ], 200);

        } catch (\Exception $e) {
            Log::error("Error processing order: " . $e->getMessage());

            return response()->json([
                'message' => 'Đã xảy ra lỗi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function changeMyOrder(Request $request)
    {
        try{
            $userId = auth()->id();
            $user = auth()->user();

            $order = Order::where('id', $request->order_id)
                ->where('user_id', $userId)
                ->first();

            if (empty($order)) {
                return response()->json([
                    'message' => 'Không tìm thấy order',
                ], 404);
            }

            if($request->hasFile('file'))
            {
                $request->validate([
                    'file' => 'file|mimes:jpg,jpeg,png,mp4|max:5120'
                ]);

                $url_file = $this->handleUploadImage($request, 'file', 'Ghi_chu_KH');
            }

            $note = [
                "reason" => $request->reason ?? "",
                "account_info" => [
                    "account_number" => $request->account_number ?? "",
                    "bank_name" => $request->bank_name ?? "",
                    "account_holder" => $request->account_holder ?? "",
                ],
                "file_note" => $url_file
            ];

            $note_json = json_encode($note);

            $order->update([
                'note_user' => $note_json,
                'check_refund' => $request->check_refund  // 0 là chở xử lý - 1 đã hoàn tiền
            ]);

            $emailAdmin = 'quyendvph34264@gmail.com';
            Mail::to($emailAdmin)->send(new NotiDestroyMail($user, $order));

            return response()->json([
                'message' => 'Cập nhật hủy đơn thành công, vui lòng chờ shop xử lý!',
                'data' => $order
            ], 200);

        }
        catch (\Exception $e) {
            Log::error("Error processing order: " . $e->getMessage());

            return response()->json([
                'message' => 'Đã xảy ra lỗi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }
    public function handleGoogleCallback()
    {
        try {
            $user = Socialite::driver('google')->user();
            // return $user->id;
            $finduser = User::where('google_id', $user->id)->first();
            $nameParts = explode(' ', $user->name);
            $firstName = array_shift($nameParts); // Lấy từ đầu tiên
            $lastName = implode(' ', $nameParts); // Phần còn lại là họ

            if ($finduser) {
                Auth::login($finduser);
                $user = User::where('email')->first();

                JWTAuth::factory()->setTTL(60); // Đặt thời gian sống của token
                // $token = JWTAuth::claims(['sub' => $user->id]);
                $token = JWTAuth::fromUser($user);
                if (!$token) {
                    return response()->json(['error' => 'Invalid Credentials'], 401);
                }
                return redirect()->intended('http://localhost:5173');
            } else {
                $newUser = User::updateOrCreate(['email' => $user->email], [
                    'google_id' => $user->id,
                    'firt_name' => $firstName,
                    'last_name' => $lastName,
                    'role_id' => 4,
                    'password' => encrypt('123456789')

                ]);
                Auth::login($newUser);
                return redirect()->intended('http://localhost:5173');
            }
        } catch (Exception $e) {
            dd($e->getMessage());
        }
    }
    public function googleLoginJWT(Request $request)
    {
        $token = $request->input('token');
        // dd($token);
        // Tạo client Google API
        $client = new Client();
        $client->setClientId(env('GOOGLE_CLIENT_ID')); // Client ID của bạn

        // Xác thực token
        $payload = $client->verifyIdToken($token);
        if ($payload) {
            $email = $payload['email'];
            $name = $payload['name'];

            // Tách tên thành tên đầu và họ
            $nameParts = explode(' ', $name);
            $firstName = array_shift($nameParts); // Lấy tên đầu tiên
            $lastName = implode(' ', $nameParts); // Lấy họ (phần còn lại)

            $id = $payload['sub'];

            // Tìm hoặc tạo người dùng
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'google_id' => $id,
                    'firt_name' => $firstName, // Sử dụng tên đầu
                    'last_name' => $lastName,   // Lưu họ
                    'email' => $email,
                    'password' => encrypt('123456789'),
                    'role_id' => 3,
                    'status' => 'active',
                ]
            );

            // Tạo JWT token
            $jwtToken = JWTAuth::fromUser($user);
            return response()->json([
                'message' => 'Successfully logged in',
                'data' => $user,
                'token' => $jwtToken,
            ]);
        }


        return response()->json(['error' => 'Invalid token'], 401);
    }

}
