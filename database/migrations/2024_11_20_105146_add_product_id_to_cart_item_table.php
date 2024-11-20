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
            // Thêm cột product_id
            $table->unsignedBigInteger('product_id')->nullable()->after('product_variant_id');

            // Thiết lập khóa ngoại liên kết với bảng products
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_item', function (Blueprint $table) {
           // Xóa khóa ngoại và cột product_id khi rollback
           $table->dropForeign(['product_id']);
           $table->dropColumn('product_id');
        });
    }
};
