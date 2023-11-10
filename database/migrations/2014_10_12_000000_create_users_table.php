<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Query\Expression;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('last_name');
            $table->string('first_name');
            $table->string('image')->nullable();
            $table->enum('sex',['Male', 'Female', 'Other']);
            $table->string('email')->unique();
            $table->string('email_prof')->unique()->nullable();
            $table->string('address');
            $table->date('date_birth')->format('d/m/Y');
            $table->string('place_birth');
            $table->string('status')->default('active');
            $table->string('nationality');
            $table->string('phone');
            $table->string('phone_emergency')->nullable();
            $table->enum('Family_situation',['Single','Married','Divorce','Widow']);
            $table->integer('nb_children');
            $table->string('level_studies');
            $table->string('specialty');
            $table->enum('sivp',['Yes','No']);
            $table->string('registration');
            $table->string('carte_id')->nullable();
            $table->string('duration_sivp')->nullable();
            $table->integer('cin')->unique()->nullable();
            $table->date('delivery_date_cin')->nullable();
            $table->string('delivery_place_cin')->nullable();
            $table->string('num_passport')->unique()->nullable();
            $table->date('integration_date');
            $table->string('password');
            $table->longText('motivation');
            $table->integer('pwd_reset_admin');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('regime_social');
            $table->string('text')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
};
