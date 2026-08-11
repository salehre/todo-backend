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
        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('message_id')
                ->constrained('group_messages')
                ->cascadeOnDelete();

            $table->enum('type', ['image', 'file', 'voice']);
            $table->string('path', 255);
            $table->string('name', 255);
            $table->unsignedInteger('size');
            $table->unsignedInteger('voice_duration')->nullable();
            $table->integer('position')->default(0);
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_attachments');
    }
};
