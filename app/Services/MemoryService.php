<?php

namespace App\Services;

use App\Models\Memory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MemoryService
{
    protected OllamaService $ollama;

    public function __construct(OllamaService $ollama)
    {
        $this->ollama = $ollama;
    }

    /**
     * Search top relevant memories for a user using cosine distance in pgvector (<=>).
     *
     * @param int $userId
     * @param string $queryText
     * @param int $limit
     * @return array Array of memory objects/texts
     */
    public function searchSimilarMemories(int $userId, string $queryText, int $limit = 3): array
    {
        $embedding = $this->ollama->generateEmbedding($queryText);
        if (empty($embedding)) {
            return [];
        }

        $vectorString = '[' . implode(',', $embedding) . ']';

        try {
            $results = DB::select("
                SELECT id, memory_text, (embedding <=> ?::vector) AS distance
                FROM memories
                WHERE user_id = ?
                ORDER BY distance ASC
                LIMIT ?
            ", [$vectorString, $userId, $limit]);

            return $results;
        } catch (\Exception $e) {
            Log::error('Vector search failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate embedding and save a new memory record.
     *
     * @param int $userId
     * @param string $text
     * @return Memory|null
     */
    public function saveMemory(int $userId, string $text): ?Memory
    {
        $text = trim($text);
        if (empty($text)) {
            return null;
        }

        $embedding = $this->ollama->generateEmbedding($text);
        if (empty($embedding)) {
            return null;
        }

        $vectorString = '[' . implode(',', $embedding) . ']';

        try {
            $record = DB::selectOne("
                INSERT INTO memories (user_id, memory_text, embedding, created_at, updated_at)
                VALUES (?, ?, ?::vector, NOW(), NOW())
                RETURNING id, user_id, memory_text, created_at, updated_at
            ", [$userId, $text, $vectorString]);

            if ($record) {
                return Memory::find($record->id);
            }
        } catch (\Exception $e) {
            Log::error('Save memory failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Optional background fact extraction from conversation turn.
     */
    public function extractAndStoreFacts(int $userId, string $userMessage, string $companionResponse): void
    {
        try {
            $prompt = [
                [
                    'role' => 'system',
                    'content' => "Tugasmu mengekstrak fakta personal penting tentang user (seperti hobi, nama panggilan, kesukaan, cerita hidup, atau preferensi) dari percakapan berikut.\n"
                        . "Jika ada fakta penting tentang user, tuliskan dalam 1 kalimat ringkas dan jelas dalam bahasa Indonesia (contoh: 'User suka minum kopi tanpa gula di pagi hari').\n"
                        . "Jika tidak ada fakta penting baru atau hanya obrolan umum/sapaan singkat, balas hanya dengan kata: NONE",
                ],
                [
                    'role' => 'user',
                    'content' => "User: {$userMessage}\nCompanion: {$companionResponse}",
                ],
            ];

            $result = trim($this->ollama->chat($prompt));
            if (!empty($result) && strtoupper($result) !== 'NONE' && !str_contains(strtoupper($result), 'NONE')) {
                // Remove quotes if any
                $cleanFact = trim($result, " \"'\n\r\t");

                // Ignore if it contains template placeholder or is too short
                if (strlen($cleanFact) < 10 || preg_match('/\[User Name\]|NONE/i', $cleanFact)) {
                    return;
                }

                // Check if identical or very similar fact already exists for this user
                $exists = Memory::where('user_id', $userId)
                    ->where('memory_text', $cleanFact)
                    ->exists();

                if (!$exists) {
                    $this->saveMemory($userId, $cleanFact);
                    Log::info("Extracted memory for user {$userId}: {$cleanFact}");
                }
            }
        } catch (\Exception $e) {
            Log::warning('Fact extraction failed: ' . $e->getMessage());
        }
    }
}
