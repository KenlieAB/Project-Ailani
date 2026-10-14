<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companion_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('companion_name')->default('Ailani');
            $table->text('system_prompt');
            $table->string('live2d_model_path')->default('/live2d/models/ailani/ailani.model3.json');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companion_settings');
    }
};
