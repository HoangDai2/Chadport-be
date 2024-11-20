<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            // Kiểm tra và thêm cột `col_id` nếu chưa tồn tại
            if (!Schema::hasColumn('products', 'col_id')) {
                $table->unsignedBigInteger('col_id')->nullable();
                $table->foreign('col_id')->references('id')->on('colors')->onDelete('cascade');
            }
    
            // Kiểm tra và thêm cột `size_id` nếu chưa tồn tại
            if (!Schema::hasColumn('products', 'size_id')) {
                $table->unsignedBigInteger('size_id')->nullable();
                $table->foreign('size_id')->references('id')->on('sizes')->onDelete('cascade');
            }
        });
    }
    
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            // Xóa cột `col_id` nếu tồn tại
            if (Schema::hasColumn('products', 'col_id')) {
                $table->dropForeign(['col_id']);
                $table->dropColumn('col_id');
            }
    
            // Xóa cột `size_id` nếu tồn tại
            if (Schema::hasColumn('products', 'size_id')) {
                $table->dropForeign(['size_id']);
                $table->dropColumn('size_id');
            }
        });
    }
    
};
