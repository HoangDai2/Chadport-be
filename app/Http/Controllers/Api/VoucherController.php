<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\VoucherResource;
use App\Models\Voucher;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $vouchers = Voucher::all();
        return response()->json([
            'message' => 'Danh sách voucher',
            'data' => $vouchers
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:255|unique:vouchers,code',
            'discount_type' => 'required|string|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'expires_at' => 'required|date|after:now',
            'usage_limit' => 'required|integer|min:1',
            'used_count' => 'nullable|integer|min:0',
        ]);
    
        $newVoucher = Voucher::create($request->all());
        return response()->json([
            'message' => 'Voucher created successfully',
            'data' => $newVoucher
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $voucher = Voucher::findOrFail($id);
        return response()->json([
            'message' => 'Voucher found',
            'data' => $voucher
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $voucher)
    {
        $voucher = Voucher::findOrFail($voucher);
        $voucher->update($request->only([
            'code',
            'discount_type',
            'discount_value',
            'expires_at',
            'usage_limit',
            'used_count',
        ]));
        
        return response()->json([
            'message' => 'Voucher updated successfully',
            'data' => $voucher
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $voucher = Voucher::findOrFail($id);
        $voucher->delete();
        
        return response()->json([
            'message' => 'Xóa thành công!!'
        ], 200);
    }
}
