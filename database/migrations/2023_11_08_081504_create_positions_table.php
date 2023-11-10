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
        Schema::create('positions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('job_name');
            $table->string('status')->default("active");
            $table->string('description',5000)->nullable();
            $table->string('title');
            // $table->unsignedBigInteger('role_id')->index('positions_role_id_foreign');
            $table->timestamps(); 
            $table->softDeletes();
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
