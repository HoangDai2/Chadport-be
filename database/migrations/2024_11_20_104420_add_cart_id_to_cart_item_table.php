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
            $table->unsignedBigInteger('cart_id')->nullable()->after('user_id'); // Thêm cột cart_id
            $table->foreign('cart_id')->references('id')->on('cart')->onDelete('cascade'); // Ràng buộc khóa ngoại
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_item', function (Blueprint $table) {
            $table->dropForeign(['cart_id']); // Xóa ràng buộc khóa ngoại
            $table->dropColumn('cart_id'); // Xóa cột cart_id
        });
    }
};
