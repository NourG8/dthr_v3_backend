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
        Schema::create('position_users', function (Blueprint $table) {
            $table->increments('id');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->unsignedBigInteger('position_id')->index('position_users_position_id_foreign');
            $table->unsignedBigInteger('user_id')->index('position_users_user_id_foreign');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('position_users');
    }
};
