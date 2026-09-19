<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->foreignId('runbook_id')->nullable()->after('created_by')->constrained('runbooks')->nullOnDelete();
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('from_runbook')->default(false)->after('priority');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('runbook_id');
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('from_runbook');
        });
    }
};
