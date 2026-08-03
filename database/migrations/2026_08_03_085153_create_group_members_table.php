<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_id')
                ->constrained('groups')
                ->onDelete('cascade');

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->timestamp('created_at')->nullable();
            // فقط created_at داره (بدون updated_at)، پس از timestamp() به‌جای timestamps() استفاده کردیم
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_members');
    }
};
