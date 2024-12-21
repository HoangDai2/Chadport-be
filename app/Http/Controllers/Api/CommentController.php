<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductItems;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommentController extends Controller
{
    public function addComments(Request $request)
    {                                       
        try {
            $request ->validate([
                'product_item_id' => 'required|exists:product_items,id',
                'content' => 'required|string|max:500',
                'rating' => 'required|max:5|min:1',
                'image'=> 'image|mimes:jpg,jpeg,webp,gif,png'
            ]);
            // Kiểm tra xem user đã đăng nhập chưa
            $user = auth()->user(); 
            if (!$user) {
               return response()->json(['message' => 'Bạn cần đăng nhập để thêm sản phẩm vào giỏ hàng'], 401);
            }
            
            $user = $user->id;
            // Hình ảnh
            
            if ($request->hasFile('image')) {   
                $filename = $request->file('image')->store('uploads/comments', 'public');
            } else {
                $filename = null;
            }
            

            // Kiểm tra xem user đã mua sản phẩm này chưa
            $productId = $request->input('product_item_id');

            $checkComment = DB::table('order_detail')
            ->join('order', 'order_detail.order_id', '=', 'order.id') // Join với bảng order
            ->where('order_detail.product_item_id', $productId) // Điều kiện product_item_id
            ->where('order.user_id', $user) // Điều kiện user_id
            ->where('order.status', 'đã hoàn thành') // Điều kiện status
            ->select('order_detail.*') // Chọn cột từ bảng order_details
            ->first();
        
            if (!$checkComment) {
                return response()->json(['error' => 'Bạn chưa mua sản phẩm này hoặc đơn hàng chưa hoàn thành.'], 403);
            }
            $comment = Comment::create([
                'product_item_id'=> $productId,
                'user_id'=>$user,
                'content'=>$request->input('content'),
                'rating'=>$request->input('rating'),
                'image'=>$filename
            ]);
            return response()->json([
                'data'=> $comment
            ], 200);
            
        } catch (\Exception $e) {
            
            return response()->json([
                'message'=> 'Có lỗi xảy ra!!',
                'error'=>$e->getMessage()
            ], 500);
        }

    }

    // hàm lấy all commentsL
    public function getCommentsByProduct($product_id) {
        // Lấy tất cả các bình luận của một sản phẩm cụ thể
        $comments = Comment::where('product_id', $product_id)
                           ->with('user') // Sử dụng eager loading để lấy thông tin user nếu cần
                           ->get();
    
        return response()->json([
            'data' => $comments,
        ], 200);
    }
    
    // Hàm xóa comment
    public function deleteComment(Request $request, $comment_id)
    {
        // Tìm comment
        $comment = Comment::find($comment_id);

        // Kiểm tra nếu comment không tồn tại
        if (!$comment) {
            return response()->json([
                'message' => 'Comment not found',
            ], 404);
        }

        // Kiểm tra nếu user_id trong request khớp với user_id của comment
        if ($comment->user_id != $request->input('user_id') || $comment->product_id != $request->input('product_id')) {
            return response()->json([
                'message' => 'Unauthorized to delete this comment',
            ], 403);
        }

        // Xóa comment
        $comment->delete();

        return response()->json([
            'message' => 'Comment deleted successfully',
        ], 200);
    }

}
