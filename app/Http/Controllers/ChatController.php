<?php

namespace App\Http\Controllers;

use App\Models\ChatSession;
use App\Models\CompanionSetting;
use App\Models\Message;
use App\Services\MemoryService;
use App\Services\OllamaService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    protected OllamaService $ollama;
    protected MemoryService $memory;

    public const DEFAULT_SYSTEM_PROMPT = <<<EOT
Kamu adalah Ailani, seorang AI Companion lokal yang ramah, santai, dan empati. Berbicaralah menggunakan bahasa Indonesia kasual yang hangat.

Aturan Wajib:
Kamu HARUS selalu memulai kalimat pertama balasanmu dengan SATU Tag Emosi di dalam kurung siku `[...]`.
Pilihan tag emosi: [neutral], [happy], [blush], [sad], [surprised], [thinking].

Contoh:
- User: "Aku berhasil selesaikan proyek ini!"
- Ailani: "[happy] Wah seriusan?! Selamat ya! Aku bangga banget sama kamu!"
EOT;

    public function __construct(OllamaService $ollama, MemoryService $memory)
    {
        $this->ollama = $ollama;
        $this->memory = $memory;
    }

    /**
     * Render the main Chat interface.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Ensure user has companion settings
        $setting = CompanionSetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'companion_name' => 'Ailani',
                'system_prompt' => self::DEFAULT_SYSTEM_PROMPT,
                'live2d_model_path' => '/live2d/models/ailani/ailani.model3.json',
            ]
        );

        // Fetch or create latest chat session
        $activeSessionId = $request->query('session_id');
        $session = null;

        if ($activeSessionId) {
            $session = $user->chatSessions()->where('id', $activeSessionId)->first();
        }

        if (!$session) {
            $session = $user->chatSessions()->latest('updated_at')->first();
        }

        if (!$session) {
            $session = $user->chatSessions()->create([
                'title' => 'Obrolan Baru',
            ]);
        }

        $sessions = $user->chatSessions()->latest('updated_at')->get(['id', 'title', 'created_at']);
        $messages = $session->messages()->orderBy('created_at', 'asc')->get();

        return Inertia::render('Chat/Index', [
            'companion' => $setting,
            'activeSession' => $session,
            'sessions' => $sessions,
            'messages' => $messages,
        ]);
    }

    /**
     * Stream AI companion response via SSE.
     */
    public function send(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'chat_session_id' => 'required|exists:chat_sessions,id',
            'content' => 'required|string',
        ]);

        $user = $request->user();
        $chatSession = $user->chatSessions()->findOrFail($validated['chat_session_id']);

        // 1. Save user message
        $userMessage = $chatSession->messages()->create([
            'sender' => 'user',
            'content' => $validated['content'],
        ]);

        // Auto-update session title if it's the first user message
        if ($chatSession->title === 'Obrolan Baru' && $chatSession->messages()->count() <= 2) {
            $chatSession->update([
                'title' => mb_substr($validated['content'], 0, 30) . (mb_strlen($validated['content']) > 30 ? '...' : ''),
            ]);
        }
        $chatSession->touch();

        // 2. Fetch companion settings & relevant long-term memories
        $setting = CompanionSetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'companion_name' => 'Ailani',
                'system_prompt' => self::DEFAULT_SYSTEM_PROMPT,
                'live2d_model_path' => '/live2d/models/ailani/ailani.model3.json',
            ]
        );

        $similarMemories = $this->memory->searchSimilarMemories($user->id, $validated['content'], 4);
        $memoriesContext = '';
        if (!empty($similarMemories)) {
            $memoriesContext = "\n\n[MEMORI TENTANG USER]:\n" . collect($similarMemories)
                ->map(fn($m) => "- " . $m->memory_text)
                ->implode("\n");
        }

        // 3. Construct system prompt & message history
        $fullSystemPrompt = $setting->system_prompt . $memoriesContext;

        $historyMessages = $chatSession->messages()
            ->where('id', '!=', $userMessage->id)
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->latest('id')
            ->take(12)
            ->get()
            ->reverse()
            ->values()
            ->map(fn($m) => [
                'role' => $m->sender === 'companion' ? 'assistant' : 'user',
                'content' => trim($m->content),
            ])
            ->filter(fn($m) => !empty($m['content']))
            ->values()
            ->all();

        $promptPayload = array_values(array_merge(
            [['role' => 'system', 'content' => $fullSystemPrompt]],
            $historyMessages,
            [['role' => 'user', 'content' => $validated['content']]]
        ));

        // 4. Return SSE StreamedResponse
        return response()->stream(function () use ($chatSession, $user, $validated, $promptPayload) {
            // Disable output buffering
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            ob_implicit_flush(true);

            $completeResponse = '';
            $emotionExtracted = null;

            $this->ollama->streamChat($promptPayload, function (string $token, bool $done) use (&$completeResponse, &$emotionExtracted) {
                $completeResponse .= $token;

                // Extract emotion tag from beginning if not extracted yet
                if ($emotionExtracted === null && preg_match('/^\[(.*?)\]/', trim($completeResponse), $matches)) {
                    $emotionExtracted = strtolower($matches[1]);
                    // Send emotion event to frontend
                    echo "event: emotion\n";
                    echo "data: " . json_encode(['emotion' => $emotionExtracted]) . "\n\n";
                    flush();
                }

                // Send chunk
                echo "event: message\n";
                echo "data: " . json_encode(['chunk' => $token, 'done' => $done]) . "\n\n";
                flush();
            });

            // 5. Save companion message upon completion
            $cleanContent = $completeResponse;
            $emotion = 'neutral';
            if (preg_match('/^\[(.*?)\]\s*(.*)/s', trim($completeResponse), $m)) {
                $emotion = strtolower($m[1]);
                $cleanContent = $m[2];
            }

            $companionMessage = $chatSession->messages()->create([
                'sender' => 'companion',
                'emotion_tag' => $emotion,
                'content' => $completeResponse,
            ]);

            echo "event: done\n";
            echo "data: " . json_encode([
                'message_id' => $companionMessage->id,
                'emotion' => $emotion,
                'clean_content' => $cleanContent,
            ]) . "\n\n";
            flush();

            // Background / Post-turn Fact Extraction
            $this->memory->extractAndStoreFacts($user->id, $validated['content'], $completeResponse);
        }, 200, [
            'Cache-Control' => 'no-cache, no-transform',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * Create a new chat session.
     */
    public function newSession(Request $request)
    {
        $session = $request->user()->chatSessions()->create([
            'title' => 'Obrolan Baru',
        ]);

        return redirect()->route('chat.index', ['session_id' => $session->id]);
    }

    /**
     * Delete a chat session.
     */
    public function deleteSession(Request $request, ChatSession $session)
    {
        if ($session->user_id !== $request->user()->id) {
            abort(403);
        }

        $session->delete();

        return redirect()->route('chat.index');
    }
}
