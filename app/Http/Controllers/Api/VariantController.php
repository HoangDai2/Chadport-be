<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class VariantController extends Controller
{
     // Lấy thông tin sản phẩm cùng các biến thể
    public function show($id)
    {
         // Tìm sản phẩm theo ID và load các biến thể
        $product = Product::with([
             'productItems.color', // Lấy thông tin màu sắc
             'productItems.size'   // Lấy thông tin kích thước
        ])->find($id);
    
         // Nếu không tìm thấy sản phẩm
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
    
         // Trả về thông tin sản phẩm cùng các biến thể
        return response()->json([
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'title' => $product->title,
                'description' => $product->description,
                'status' => $product->status,
                'price' => $product->price,
                'price_sale' => $product->price_sale,
                'total_quantity' => $product->total_quantity,
                'image_product' => $product->image_product,
                 'image_description' => json_decode($product->image_description), // Convert JSON string to array
             ],
             'variants' => $product->productItems->map(function ($variant) {
                 return [
                     'id' => $variant->id,
                     'description' => $variant->description,
                     'quantity' => $variant->quantity,
                     'status' => $variant->status,
                     'type' => $variant->type,
                     'color' => $variant->color ? $variant->color->hex : null, // Lấy tên màu
                     'size' => $variant->size ? $variant->size->name : null,   // Lấy tên size
                 ];
             }),
         ], 200);
    }
    
    // Tạo mới variant
    public function creates(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id', // Validate product_id
            'size_id' => 'required|exists:sizes,id',
            'color_id' => 'required|exists:colors,id',
            'quatity' => 'required|integer|min:1',
        ]);

        $variant = ProductVariant::create([
            'product_id' => $request->input('product_id'), // Lưu product_id
            'size_id' => $request->input('size_id'),
            'color_id' => $request->input('color_id'),
            'quatity' => $request->input('quatity'),
        ]);

        return response()->json(['data' => $variant], 201);
    }

    // Lấy tất cả variants
    public function GetAll(Request $request)
    {
        $variants = ProductVariant::with(['size', 'color'])->get();

        return response()->json([
            'data' => $variants
        ], 200);
    }

    // Cập nhật variant
    public function updates(Request $request, string $id)
    {
        $validated = $request->validate([
            'size_id' => 'required|exists:sizes,id',
            'color_id' => 'required|exists:colors,id',
            'quatity' => 'required|integer',
        ]);

        if ($validated) {
            $variant = ProductVariant::where('id', $id)->update([
                'size_id' => $request->input('size_id'),
                'color_id' => $request->input('color_id'),
                'quatity' => $request->input('quatity'),
            ]);
        }

        return response()->json([
            'data' => $variant
        ], 200);
    }

    // Xóa variant
    public function destroy(Request $request, string $id)
    {
        $variant = ProductVariant::where('id', $id)->delete();

        return response()->json([
            'message' => 'Variant deleted successfully'
        ], 200);
    }
}
