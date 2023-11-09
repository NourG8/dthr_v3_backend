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
        Schema::create('team_users', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('is_leader');
            $table->integer('is_deleted')->default(0);
            $table->date('integration_date')->nullable();
            $table->unsignedBigInteger('user_id')->index('team_users_user_id_foreign');
            $table->unsignedBigInteger('team_id')->index('team_users_team_id_foreign');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_users');
    }
};
