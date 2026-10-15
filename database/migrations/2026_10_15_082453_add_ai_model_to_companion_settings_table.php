<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companion_settings', function (Blueprint $table) {
            $table->string('ai_model')->default('qwen2.5:3b')->after('companion_name');
        });
    }

    public function down(): void
    {
        Schema::table('companion_settings', function (Blueprint $table) {
            $table->dropColumn('ai_model');
        });
    }
};
