<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class ProductControllers extends Controller
{
    public function createProducts(Request $request)
    {
        // Lấy các dữ liệu đầu vào trừ image_description
        $data = $request->only([
            'cat_id',
            'title',
            'name',
            'status',
            'col_id',
            'size_id',
            'brand_id',
            'description',
            'quantity',
            'image_product',
            'price',
            'price_sale',
            'type',
        ]);

        // Xác thực dữ liệu đầu vào, bao gồm các ảnh trong image_description
        $validated = $request->validate([
            'cat_id' => 'required|exists:categories,id',
            'title' => 'required|max:255',
            'name' => 'required|max:500',
            'status' => 'required|in:active,inactive',
            'col_id' => 'nullable|exists:colors,id',
            'size_id' => 'nullable|exists:sizes,id',
            'brand_id' => 'nullable|exists:brands,id', // Trường này là optional
            'description' => 'nullable|string',
            'quantity' => 'required|integer|min:0',
            'image_product' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'image_description' => 'nullable|array',
            'image_description.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'price' => 'required|numeric|min:0',
            'price_sale' => 'nullable|numeric|min:0',
            'type' => 'nullable|string|max:50',
        ]);


        $data = $validated;
        $imagePaths = [];
        // Lưu ảnh chính của sản phẩm
        if ($request->hasFile('image_product')) {
            $imageProductPath = $request->file('image_product')->store('images', 'public');
            $data['image_product'] = $imageProductPath; // Lưu đường dẫn ảnh chính
        }

        // Xử lý việc upload các file và lưu đường dẫn vào mảng
        if ($request->hasFile('image_description')) {
            $imagePaths = [];  // Khởi tạo mảng để lưu đường dẫn
            foreach ($request->file('image_description') as $file) {
                $path = $file->store('images', 'public');
                $imagePaths[] = $path; // Thêm đường dẫn ảnh vào mảng
                Log::info($file->getClientMimeType());
            }
            $data['image_description'] = json_encode($imagePaths);  // Lưu đường dẫn ảnh dưới dạng JSON
        }


        // Tạo sản phẩm mới sau khi đã xử lý và xác thực dữ liệu
        $product = Product::create($data);

        return response()->json([
            'data' => $product,
            'imagePaths' => $imagePaths, // Log để kiểm tra
            'message' => 'Product created with images successfully'
        ], 201);
    }

    public function showProduct(Request $request)
    {
        // Số lượng sản phẩm hiển thị trên mỗi trang
        $perPage = 10;

        // Sử dụng phân trang Laravel với số lượng sản phẩm trên mỗi trang là $perPage
        $listProduct = Product::paginate($perPage);

        // Trả về danh sách sản phẩm và thông tin phân trang
        return response()->json([
            'data' => $listProduct->items(),
            'current_page' => $listProduct->currentPage(),
            'last_page' => $listProduct->lastPage(),
            'total' => $listProduct->total(),
        ], 200);
    }

    public function showShopProducts(Request $request)
    {
        // Số sản phẩm trên mỗi trang
        $perPage = 15;

        // Lấy danh sách sản phẩm với phân trang
        $listProduct = Product::paginate($perPage);

        // Trả về dữ liệu sản phẩm và thông tin phân trang
        return response()->json([
            'data' => $listProduct->items(), // Danh sách sản phẩm
            'current_page' => $listProduct->currentPage(), // Trang hiện tại
            'last_page' => $listProduct->lastPage(), // Trang cuối cùng
            'total' => $listProduct->total(), // Tổng số sản phẩm
            'per_page' => $perPage, // Số sản phẩm mỗi trang
        ], 200);
    }




    // Phương thức để lấy thông tin chi tiết của sản phẩm theo ID
    public function showDetail($id)
    {
        // Tìm Product theo ID và eager load các quan hệ: variants, size, color
        $product = Product::with(['variants.size', 'variants.color'])->find($id);

        // Nếu sản phẩm không tồn tại, trả về lỗi 404
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // Xử lý nhóm dữ liệu variants theo size
        $groupedVariants = [];
        foreach ($product->variants as $variant) {
            $sizeName = $variant->size->name ?? 'Unknown';
            $colorInfo = [
                'color_id' => $variant->color->id ?? null,
                'name' => $variant->color->name ?? null,
                'hex' => $variant->color->hex ?? null,
            ];

            // Nhóm color theo size
            if (!isset($groupedVariants[$sizeName])) {
                $groupedVariants[$sizeName] = [
                    'size_id' => $variant->size_id,
                    'size_name' => $sizeName,
                    'colors' => [],
                ];
            }
            $groupedVariants[$sizeName]['colors'][] = $colorInfo;
        }

        // Chuyển mảng thành danh sách có chỉ số
        $groupedVariants = array_values($groupedVariants);

        // Thêm dữ liệu đã nhóm vào product
        $product->grouped_variants = $groupedVariants;

        // Trả về thông tin sản phẩm cùng danh sách kích thước và màu sắc
        return response()->json($product);
    }


    public function updateProduct(Request $request, $id)
    {
        try {
            // Xác thực các dữ liệu đầu vào
            $validated = $request->validate([
                'cat_id' => 'sometimes|exists:categories,id',
                'title' => 'sometimes|max:255',
                'name' => 'sometimes|max:500',
                'status' => 'sometimes|in:active,inactive',
                'col_id' => 'nullable|exists:colors,id',
                'size_id' => 'nullable|exists:sizes,id',
                'brand_id' => 'nullable|exists:brands,id',
                'description' => 'nullable|string',
                'quantity' => 'sometimes|integer|min:0',
                'image_product' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
                'image_description' => 'nullable|array',
                'image_description.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
                'price' => 'sometimes|numeric|min:0',
                'price_sale' => 'nullable|numeric|min:0',
                'type' => 'nullable|string|max:50',
                'size' => 'nullable|string|max:20',
                'color' => 'nullable|string|max:20',
            ]);


            // Lấy dữ liệu đầu vào từ yêu cầu
            $data = $validated;
            // Lưu ảnh chính của sản phẩm
            if ($request->hasFile('image_product')) {
                $imageProductPath = $request->file('image_product')->store('images', 'public');
                $data['image_product'] = $imageProductPath; // Lưu đường dẫn ảnh mới
            } else {
                // Nếu không có ảnh mới, giữ lại ảnh cũ (không thay đổi)
                $existingProduct = Product::find($id);
                if ($existingProduct && $existingProduct->image_product) {
                    $data['image_product'] = $existingProduct->image_product; // Giữ lại ảnh cũ
                }
            }



            // Xử lý upload các file hình ảnh mô tả nếu có
            if ($request->hasFile('image_description')) {
                $imagePaths = [];
                foreach ($request->file('image_description') as $file) {
                    $path = $file->store('images', 'public');
                    $imagePaths[] = $path;
                }
                $data['image_description'] = json_encode($imagePaths);
            }

            $product = Product::find($id);


            // Cập nhật thông tin sản phẩm
            $product->update($data);
            // Lấy tất cả dữ liệu sản phẩm sau khi cập nhật

            // Trả về phản hồi JSON với dữ liệu đã cập nhật
            return response()->json([
                'status' => 'success',
                'message' => 'Sản phẩm đã được cập nhật thành công!',
                'data' => $product
            ], 200);
        } catch (\Exception $e) {
            // Xử lý lỗi nếu có
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi cập nhật sản phẩm!',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function destroy(Request $request, string $id)
    {
        try {
            // Tìm sản phẩm theo id
            $product = Product::where('id', $id)->first();

            // Kiểm tra xem sản phẩm có tồn tại không
            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Không tìm thấy sản phẩm để xóa!'
                ], 404);
            }

            // Nếu sản phẩm tồn tại, thực hiện xóa
            $product->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Sản phẩm đã được xóa thành công!',
                'data' => $product
            ], 200);
        } catch (\Exception $e) {
            // Xử lý lỗi nếu có
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi xóa sản phẩm!',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getProductsByCategory($cat_id)
    {
        // Lấy tất cả sản phẩm theo danh mục
        $products = Product::where('cat_id', $cat_id)->get();

        // Kiểm tra nếu không có sản phẩm nào
        if ($products->isEmpty()) {
            return response()->json(['message' => 'No products found in this category'], 404);
        }

        // Trả về danh sách sản phẩm dưới dạng JSON
        return response()->json([
            'data' => $products
        ], 200);
    }
    public function getCategories(Request $request)
    {
        $perPage = 3; // Số lượng items mỗi trang
        $categories = Category::paginate($perPage);

        return response()->json([
            'data' => $categories->items(), // Dữ liệu danh mục
            'current_page' => $categories->currentPage(), // Trang hiện tại
            'last_page' => $categories->lastPage(), // Trang cuối
            'total' => $categories->total(), // Tổng số danh mục
        ], 200);
    }

    public function showProductById($id)
    {
        $product = Product::find($id); // Tìm sản phẩm theo id

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404); // Nếu không tìm thấy sản phẩm
        }

        return response()->json(['data' => $product], 200); // Trả về sản phẩm tìm thấy
    }
}
