<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('pinned_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_id')
                ->constrained('groups')
                ->cascadeOnDelete();

            $table->foreignId('message_id')
                ->constrained('group_messages')
                ->cascadeOnDelete();

            $table->foreignId('pinned_by')
                ->constrained('users');

            $table->timestamp('created_at')->nullable();

            $table->unique(['group_id', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pinned_messages');
    }
};
