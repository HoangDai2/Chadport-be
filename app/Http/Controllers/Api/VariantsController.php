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
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id', // Ensure that the product exists
            'size_id' => 'required|exists:sizes,id',
            'col_id' => 'required|exists:colors,id',
            'quantity' => 'required|integer',
        ]);
    
        // Create the variant and associate it with the selected product, size, and color
        $variant = ProductVariant::create([
            'product_id' => $validated['product_id'],  // Ensure product_id is included
            'size_id' => $validated['size_id'],
            'col_id' => $validated['col_id'],
            'quantity' => $validated['quantity'],
        ]);
    
        return response()->json([
            'message' => 'Variant created successfully!',
            'data' => $variant,
        ]);
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
