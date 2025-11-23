<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConversationUserTable extends Migration
{
    public function up()
    {
        Schema::create('conversation_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']); // Prevent duplicate entries
        });
    }

    public function down()
    {
        Schema::dropIfExists('conversation_user');
    }
}
