<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAIMessagesTable extends Migration
{
    public function up()
    {
        Schema::create('a_i_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->text('prompt');
            $table->text('response');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('a_i_messages');
    }
}
