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
            'variants.color', // Lấy thông tin màu sắc
            'variants.size'   // Lấy thông tin kích thước
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
            'variants' => $product->variants->map(function ($variant) {
                return [
                    'id' => $variant->id,
                    'description' => $variant->description,
                    'quantity' => $variant->quantity,
                    'status' => $variant->status,
                    'type' => $variant->type,
                    'color' => $variant->color ? $variant->color->hex : null, // Lấy tên màu
                    'size' => $variant->size ? $variant->size->name : null,   // Lấy tên size
                    'color_id' => $variant->color_id, // Thêm color_id
                    'size_id' => $variant->size_id,   // Thêm size_id
                ];
            }),
        ], 200);
    }

    // Tạo mới variant
    public function creates(Request $request)
    {
        // Validate dữ liệu mảng
        $validated = $request->validate([
            '*.product_id' => 'required|exists:products,id', // Validate mỗi product_id
            '*.size_id' => 'required|exists:sizes,id',       // Validate mỗi size_id
            '*.color_id' => 'required|exists:colors,id',     // Validate mỗi color_id
            '*.quantity' => 'required|integer|min:1',        // Validate mỗi quantity
        ]);

        // Khởi tạo mảng để chứa các biến thể đã tạo
        $variants = [];

        // Lặp qua từng phần tử trong mảng và tạo các biến thể
        foreach ($request->all() as $variantData) {
            $variant = ProductVariant::create([
                'product_id' => $variantData['product_id'],
                'size_id' => $variantData['size_id'],
                'color_id' => $variantData['color_id'],
                'quantity' => $variantData['quantity'],
            ]);
            // Thêm biến thể đã tạo vào mảng
            $variants[] = $variant;
        }

        // Trả về phản hồi với tất cả các biến thể đã tạo
        return response()->json(['data' => $variants], 201);
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
            'quantity' => 'required|integer',
        ]);

        if ($validated) {
            $variant = ProductVariant::where('id', $id)->update([
                'size_id' => $request->input('size_id'),
                'color_id' => $request->input('color_id'),
                'quantity' => $request->input('quantity'),
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
