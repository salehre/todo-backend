<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('message_id')
                ->constrained('group_messages')
                ->onDelete('cascade');

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->string('emoji', 10);

            $table->timestamp('created_at')->nullable();
            // فقط created_at داره (بدون updated_at)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reactions');
    }
};
