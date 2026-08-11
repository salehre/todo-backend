<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_id')
                ->constrained('groups')
                ->onDelete('cascade');

            $table->foreignId('sender_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('cascade');

            $table->text('text')->nullable();

            $table->enum('type', ['text', 'system'])->default('text');

            $table->foreignId('reply_to')
                ->nullable()
                ->constrained('group_messages')
                ->onDelete('set null');

            $table->foreignId('task_id')
                ->nullable()
                ->constrained('tasks')
                ->onDelete('set null');

            $table->boolean('edited')->default(false);

            $table->enum('attachment_type', ['image', 'file', 'voice'])->nullable();
            $table->string('attachment_path', 255)->nullable();
            $table->string('attachment_name', 255)->nullable();
            $table->unsignedInteger('attachment_size')->nullable();

            $table->unsignedInteger('voice_duration')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_messages');
    }
};
