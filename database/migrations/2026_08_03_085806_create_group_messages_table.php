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

            // FK به groups، وقتی گروه حذف شد پیام‌هاش هم حذف بشن
            $table->foreignId('group_id')
                ->constrained('groups')
                ->onDelete('cascade');

            // FK به users، nullable چون ممکنه فرستنده متن نداشته باشه
            $table->foreignId('sender_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('cascade'); // اگه رفتار متفاوتی می‌خوای بگو تغییرش بدم

            $table->text('text')->nullable(); // چون پیام فقط‌عکسیمه ممکنه متن نداشته باشه

            $table->enum('type', ['text', 'system'])->default('text');

            // self-reference به همین جدول، وقتی پیام اصلی حذف شد این فیلد نال بشه
            $table->foreignId('reply_to')
                ->nullable()
                ->constrained('group_messages')
                ->onDelete('set null');

            // FK به tasks، وقتی تسک حذف شد نال بشه (todoRef)
            $table->foreignId('task_id')
                ->nullable()
                ->constrained('tasks')
                ->onDelete('set null');

            $table->boolean('pinned')->default(false);
            $table->boolean('edited')->default(false);

            $table->enum('attachment_type', ['image', 'file', 'voice'])->nullable();
            $table->string('attachment_path', 255)->nullable();
            $table->string('attachment_name', 255)->nullable();
            $table->unsignedInteger('attachment_size')->nullable();

            // فقط برای پیام صوتی، برحسب ثانیه
            $table->unsignedInteger('voice_duration')->nullable();

            $table->timestamps(); // created_at / updated_at (Null: Yes)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_messages');
    }
};
