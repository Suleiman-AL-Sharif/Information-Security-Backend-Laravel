<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->text('date')->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('related_user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->text('ServerPrivateKey')->nullable();
            $table->text('UserPublicKey')->nullable();
            $table->text('SessionKey')->nullable();
            $table->text('signature')->nullable();
            $table->boolean('key')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('servers');
    }
};
