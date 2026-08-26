<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE group_members MODIFY role ENUM('owner','admin','member') DEFAULT 'member'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE group_members MODIFY role ENUM('admin','member') DEFAULT 'member'");
    }
};
