<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->foreignId('created_by')
                ->constrained('users')
                ->onDelete('cascade'); // در صورت نیاز می‌تونی onDelete رو حذف یا تغییر بدی
            $table->timestamps(); // created_at و updated_at (Null: Yes)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
