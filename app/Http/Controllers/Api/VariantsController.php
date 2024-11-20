<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class VariantsController extends Controller
{
    // Tạo mới variant chỉ với size, color và quantity
    public function create(Request $request)
    {
        $request->validate([
            'size_id' => 'required|exists:sizes,id',
            'col_id' => 'required|exists:colors,id',
            'quantity' => 'required|integer',
        ]);

        $variant = new ProductVariant();
        $variant->size_id = $request->size_id;
        $variant->col_id = $request->col_id;
        $variant->quantity = $request->quantity;
        $variant->save();

        return response()->json([
            'message' => 'Variant created successfully!',
            'data' => $variant
        ], 201);
    }

    // Lấy thông tin variant theo ID
    public function show($id)
    {
        $variant = ProductVariant::findOrFail($id);
        return response()->json($variant);
    }

    // Lấy tất cả variants
    public function index()
{
    $variants = ProductVariant::with(['size', 'color'])->get();
    return response()->json($variants);
}


    // Cập nhật variant
    public function update(Request $request, $id)
    {
        $request->validate([
            'size_id' => 'required|exists:sizes,id',
            'col_id' => 'required|exists:colors,id',
            'quantity' => 'required|integer',
        ]);

        $variant = ProductVariant::findOrFail($id);
        $variant->size_id = $request->size_id;
        $variant->col_id = $request->col_id;
        $variant->quantity = $request->quantity;
        $variant->save();

        return response()->json([
            'message' => 'Variant updated successfully!',
            'data' => $variant
        ]);
    }

    // Xóa variant
    public function destroy($id)
    {
        $variant = ProductVariant::findOrFail($id);
        $variant->delete();

        return response()->json([
            'message' => 'Variant deleted successfully!'
        ]);
    }
}
