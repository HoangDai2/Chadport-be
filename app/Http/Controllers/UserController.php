<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function totalUser() {
        $totalUser = User::where('role_id', '4')->count();
        return response()->json([
            'Tổng người dùng là'=>$totalUser
        ]);
    }
}
