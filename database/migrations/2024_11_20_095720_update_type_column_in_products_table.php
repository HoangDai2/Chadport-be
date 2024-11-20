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
        // Thay đổi cột `type` để cho phép NULL hoặc đặt giá trị mặc định
        $table->string('type', 255)->nullable()->default('default_type')->change();
    });
}

public function down()
{
    Schema::table('products', function (Blueprint $table) {
        // Khôi phục lại cột `type` về trạng thái ban đầu (không có giá trị mặc định)
        $table->string('type', 255)->nullable(false)->default(null)->change();
    });
}

};
