<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    protected string $baseUrl;
    protected string $model;
    protected string $embeddingModel;

    public function __construct()
    {
        $this->baseUrl = config('services.ollama.base_url', env('OLLAMA_BASE_URL', 'http://localhost:11434'));
        $this->model = config('services.ollama.model', env('OLLAMA_MODEL', 'qwen2.5:3b'));
        $this->embeddingModel = config('services.ollama.embedding_model', env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text:latest'));
    }

    /**
     * Stream chat completion from Ollama.
     * Invokes $callback with each received token/string.
     *
     * @param array $messages Array of ['role' => 'system'|'user'|'assistant', 'content' => string]
     * @param callable $callback function(string $token, bool $done): void
     * @return string Complete combined response
     */
    public function streamChat(array $messages, callable $callback, ?string $model = null): string
    {
        $sanitizedMessages = array_values(array_filter($messages, fn($m) => !empty($m['content'] ?? '')));
        $targetModel = $model ?: $this->model;

        $payload = [
            'model' => $targetModel,
            'messages' => $sanitizedMessages,
            'stream' => true,
            'options' => [
                'num_ctx' => 4096,
                'temperature' => 0.7,
                'repeat_penalty' => 1.12,
                'top_p' => 0.9,
                'top_k' => 40,
                'min_p' => 0.05,
            ],
        ];

        $fullContent = '';
        $buffer = '';

        $ch = curl_init(rtrim($this->baseUrl, '/') . '/api/chat');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use (&$buffer, &$fullContent, $callback) {
            $buffer .= $chunk;
            $lines = explode("\n", $buffer);
            // Keep the last incomplete fragment in buffer
            $buffer = array_pop($lines);

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                $json = json_decode($line, true);
                if ($json && isset($json['error'])) {
                    $errMsg = "[sad] Terjadi error pada model AI: " . $json['error'];
                    Log::error("Ollama API Error: " . $json['error']);
                    $fullContent .= $errMsg;
                    $callback($errMsg, true);
                    return strlen($chunk);
                }

                if ($json && isset($json['message']['content'])) {
                    $token = $json['message']['content'];
                    $done = $json['done'] ?? false;
                    $fullContent .= $token;
                    $callback($token, $done);
                }
            }

            return strlen($chunk);
        });

        curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch)) {
            $errMsg = "[sad] Tidak dapat menghubungi Ollama: " . curl_error($ch);
            Log::error('Ollama streamChat cURL Error: ' . curl_error($ch));
            $callback($errMsg, true);
            $fullContent = $errMsg;
        } elseif ($httpCode >= 400 && empty($fullContent)) {
            $errMsg = "[sad] Ollama mengembalikan kode status HTTP {$httpCode}. Pastikan model '{$targetModel}' dapat dimuat.";
            Log::error($errMsg);
            $callback($errMsg, true);
            $fullContent = $errMsg;
        }

        curl_close($ch);

        return $fullContent;
    }

    /**
     * Generate text embedding from Ollama.
     *
     * @param string $text
     * @return array<float>
     */
    public function generateEmbedding(string $text): array
    {
        try {
            $response = Http::timeout(30)->post(rtrim($this->baseUrl, '/') . '/api/embeddings', [
                'model' => $this->embeddingModel,
                'prompt' => $text,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['embedding'] ?? [];
            }

            Log::error('Ollama embedding request failed: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('Ollama embedding exception: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Non-streaming single completion (useful for memory extraction).
     */
    public function chat(array $messages): string
    {
        try {
            $response = Http::timeout(60)->post(rtrim($this->baseUrl, '/') . '/api/chat', [
                'model' => $this->model,
                'messages' => $messages,
                'stream' => false,
            ]);

            if ($response->successful()) {
                return $response->json('message.content') ?? '';
            }
        } catch (\Exception $e) {
            Log::error('Ollama chat error: ' . $e->getMessage());
        }

        return '';
    }
}
