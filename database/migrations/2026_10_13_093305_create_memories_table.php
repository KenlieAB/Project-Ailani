<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Enable pgvector extension
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector;');

        DB::statement('
            CREATE TABLE memories (
                id BIGSERIAL PRIMARY KEY,
                user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                memory_text TEXT NOT NULL,
                embedding vector(768),
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            )
        ');

        // Create HNSW index for fast cosine similarity search
        DB::statement('CREATE INDEX ON memories USING hnsw (embedding vector_cosine_ops);');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS memories;');
    }
};
