<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cart_item', function (Blueprint $table) {
            // Thêm lại cột user_id
            $table->unsignedBigInteger('user_id')->nullable();

            // Thêm khóa ngoại liên kết đến bảng users
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_item', function (Blueprint $table) {
             // Xóa khóa ngoại
             $table->dropForeign(['user_id']);
            
             // Xóa cột user_id
             $table->dropColumn('user_id');
        });
    }
};
