<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function GetAll(Request $request)
    {
      $listcategories = Category::all();

      return response()->json([
        'data' => $listcategories
       ], 201);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function create (Request $request)
    {
        $categories_name = $request->input("name");
        $categories_status = $request->input("status");
        $categories_imageURL = $request->input("imageURL"); 

        $validated = $request->validate([
            'name' => 'required|max:50',
            'status' => 'required|in:active,inactive', 
            'imageURL' => 'required|max:255',
        ]);

        if ($validated) {
            $categoriess = Category::create([
            'name' => $categories_name,
            "status" =>  $categories_status,
            "imageURL" =>  $categories_imageURL
            ]);
        }

        return response()->json([
            'data' => $categoriess
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }
        return response()->json(['data' => $category], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CategoryRequest $request, string $id)
    {
        // dd(1);
        $categories_name = $request->input("name");
        $categories_status = $request->input("status");
        $categories_imageURL = $request->input("imageURL");

        $validated = $request->validate([
        'name' => 'required|max:255',
        'status' => 'required|in:active,inactive',
        'imageURL' => 'nullable|max:255',
        ]);

        if ($validated) {
            $categoriess = Category::where('id', $id)->update([
                'name' => $categories_name,
                'status' => $categories_status,
                'imageURL' => $categories_imageURL,
            ]);

            return response()->json([
                'data' => $categoriess
            ], 201);
    }
    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        // Find the category by ID
        $category = Category::find($id);
        // Kiểm tra có sản phẩm thuốc danh mục này hay k, nếu có thì k được xóa
        $product = Product::where('category_id', $id)->count();
        if ($product > 0) {
            return response()->json([
                'message' => 'Không thể xóa danh mục này vì có sản phẩm thuộc danh mục này',
            ], 400);
        }
        // Check if the category exists
        if (!$category) {
            return response()->json([
                'message' => 'Category not found',
            ], 404);
        }

        // Delete the category
        $category->delete();

        return response()->json(['message' => 'Xóa thành công',
            'data' => $category
        ], 200);
    }
    // Hàm khôi phục danh mục đã xóa
    public function restoreCategory($id) {
        Category::withTrashed()->find($id)->restore();
        return response()->json([
            'message' => 'Khôi phục thành công',
        ], 200);
    }
    //Hàm xóa vĩnh viễn
    public function forceDelete($id) {
        Category::withTrashed()->find($id)->forceDelete();
        return response()->json([
            'message' => 'Xóa vĩnh viễn thành công',
        ], 200);
    }
    // Hàm lấy tất cả danh mục đã xóa
    public function getDeletedCategories() {
        $categories = Category::onlyTrashed()->get();
        return response()->json([
            'data' => $categories
        ], 200);
    }
    

}