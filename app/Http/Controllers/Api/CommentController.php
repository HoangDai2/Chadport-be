<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductItems;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CommentController extends Controller
{
    public function addComments(Request $request)
    {                                       
        try {
            $request ->validate([
                'product_item_id' => 'required|exists:product_items,id',
                'content' => 'required|string|max:500',
                'rating' => 'required|max:5|min:1',
                'image'=> 'image|mimes:jpg,jpeg,webp,gif,png,mp4'
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

            //kiểm tra user đã đánh giá sản phẩm này chưa
            $existComment = DB::table('comment')
            ->where('product_item_id', $productId)
            ->where('user_id', $user)
            ->first();

            if ($existComment) {
                return response()->json(['error' => 'Bạn đã đánh giá sản phẩm này rồi.'], 403);
            }


            $comment = Comment::create([
                'product_item_id'=> $productId,
                'user_id'=>$user,
                'content'=>$request->input('content'),
                'rating'=>$request->input('rating'),
                'image'=>$filename
            ]);
            return response()->json([
                'message'=> 'Đánh giá sản phẩm thành công',
                'data'=> $comment
            ], 200);
            
        } catch (\Exception $e) {
            
            return response()->json([
                'message'=> 'Có lỗi xảy ra!!',
                'error'=>$e->getMessage()
            ], 500);
        }

    }

    private function getCommentsByProductId($productId) {
        return DB::table('comment')
            ->join('product_items', 'comment.product_item_id', '=', 'product_items.id')
            ->join('users', 'comment.user_id', '=', 'users.id')
            ->join('colors', 'product_items.color_id', '=', 'colors.id') // Liên kết với bảng colors
            ->join('sizes', 'product_items.size_id', '=', 'sizes.id')
            ->where('product_items.product_id', $productId)
            ->select(
                    'comment.content', 'comment.rating','comment.created_at', 'comment.image', 'product_items.color_id',
                    DB::raw("CONCAT(users.firt_name, ' ', users.last_name) as name "),
                    'users.image_user',
                    'colors.name as color_name',
                    'sizes.name as size_name'
                 )
            ->get();
    }

    // truyền product_id vào để lấy ra tất cả bình luận của sản phẩm đó
    public function showComments($productId) {
        $comments = $this->getCommentsByProductId($productId);
        return response()->json([
            'product_id' => $productId,
            'comments' => $comments,
        ]);
    }

    //hàm lấy tắt cả các bình luận của user 
    public function getAllCommentsByUser (Request $request) {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Bạn cần đăng nhập để thêm sản phẩm vào giỏ hàng'], 401);
         }
        $comments = Comment::where('user_id', $user->id)->get();
        return response()->json([
            'data' => $comments
        ], 200);
    } 

    // Hàm xóa comment
    public function deleteComment(string $comment_id)
    {
        try {
        
        // Tìm comment
        $comment = DB::table('comment')->where('comment_id', $comment_id)->first();

        // Kiểm tra nếu comment không tồn tại
        if (!$comment) {
            return response()->json([
                'message' => 'Comment không tồn tại',
            ], 404);
        }
        // Kiểm tra nếu user_id trong request khớp với user_id của comment
        if ($comment->user_id != auth()->user()->id) {
            return response()->json([
                'message' => 'Unauthorized to delete this comment',
            ], 403);
        }

        // Xóa comment
        DB::table('comment')->where('comment_id', $comment_id)->delete();

        return response()->json([
            'message' => 'Xóa comment thành công',
        ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Có lỗi xảy ra',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Hàm sửa comment
    public function editCommentByUser(string $comment_id, Request $request) {
        try {
                $request->setMethod('PUT');
                $params = $request->all();
                
                $comment = DB::table('comment')->where('comment_id', $comment_id)->first();
                
                // check comment
                if (!$comment) {
                    return response()->json([
                        'message' => 'Comment không tồn tại',
                    ], 404);
                }
    
                
                // check người dùng đã comment hay chauw
                if ($comment->user_id != auth()->user()->id) {
                    return response()->json([
                        'message' => 'Bạn chưa comment bài viết này',
                    ], 403);
                }
    
                //Kiểm tra ảnh
                if ($request->hasFile('image')) {
                    if ($comment->image) {      
                        Storage::disk('public')->delete($comment->image);   
                    }
                    $params['image'] =  $request->file('image')->store('uploads/comments', 'public');
                }else {
                    $params['image'] = $comment->image;
                }
                
                $params['content'] = $request->input('content', $comment->content);
    
                $params['updated_at'] = now();
                $comment = DB::table('comment')->where('comment_id', $comment_id)->update($params);
                
                return response()->json([
                    'message' => 'Comment updated successfully',
                ], 200);
            

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Có lỗi xảy ra',
                'error' => $e->getMessage()
            ]);
        }
    }

}
