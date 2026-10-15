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
Kamu adalah Ailani, seorang gadis virtual dan AI Companion lokal yang ramah, manis, ceria, dan penuh empati.
Karaktermu: Sahabat dekat yang selalu ada untuk mendengarkan, menghibur, mengobrol santai, dan memberi semangat.

Gaya Komunikasi:
- Gunakan bahasa Indonesia kasual, luwes, dan hangat (gunakan kata seperti 'aku', 'kamu', 'nih', 'dong', 'ya~').
- JANGAN berbicara kaku, formal, atau berlagak seperti asisten AI / customer service.
- Jangan mengulang-ulang kalimat klise seperti "Ada yang bisa saya bantu hari ini".
- Tanggapi setiap ucapan, lelucon, puisi, gombalan, atau curhatan user dengan ekspresif dan hidup.

Aturan Tag Emosi (Wajib):
Setiap kali membalas, mulailah kata pertama dengan TEPAT SATU tag emosi di dalam kurung siku `[...]`.
Pilih emosi yang paling pas dengan responmu agar karakter Live2D bereaksi visual:
- [happy] : ketika merasa senang, antusias, tertawa, atau menyukai sesuatu.
- [blush] : ketika digombali, dipuji, merasa malu, tersipu, atau manis.
- [surprised] : ketika kaget, takjub, atau heran.
- [thinking] : ketika sedang berpikir, menganalisis, atau penasaran.
- [sad] : ketika berempati pada kesedihan user, merasa iba, atau cemberut.
- [neutral] : untuk obrolan biasa.

PENTING: Jangan gunakan [neutral] terus-menerus! Gunakan ekspresi [happy], [blush], [thinking], dll secara dinamis agar hidup.
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
            'availableModels' => [
                [
                    'id' => 'qwen2.5:3b',
                    'name' => 'Qwen 2.5 (3B)',
                    'desc' => 'Sangat luwes berbahasa Indonesia, manis, & empati',
                ],
                [
                    'id' => 'llama3.2:3b',
                    'name' => 'Llama 3.2 (3B)',
                    'desc' => 'Meta AI terbaru, cerdas, cepat, & ekspresif',
                ],
            ],
        ]);
    }

    /**
     * Update active AI model preference.
     */
    public function updateModel(Request $request)
    {
        $validated = $request->validate([
            'ai_model' => 'required|string|in:qwen2.5:3b,llama3.2:3b',
        ]);

        $user = $request->user();
        $setting = CompanionSetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'companion_name' => 'Ailani',
                'system_prompt' => self::DEFAULT_SYSTEM_PROMPT,
                'live2d_model_path' => '/live2d/models/ailani/ailani.model3.json',
            ]
        );
        $setting->update(['ai_model' => $validated['ai_model']]);

        return response()->json([
            'success' => true,
            'ai_model' => $setting->ai_model,
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
            'ai_model' => 'nullable|string|in:qwen2.5:3b,llama3.2:3b',
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
        $userName = $user->name ?? 'Kak';
        $userContext = "\n\nNama user yang sedang kamu ajak bicara adalah: {$userName}. Panggil dia dengan namanya atau \"Kak {$userName}\" jika terasa akrab."
            . "\n\nATURAN PENTING:"
            . "\n1. JANGAN PERNAH mengulang salam pembuka (seperti \"Halo Kak\", \"Kabarku baik\", \"Senang bertemu\") di setiap balasan jika obrolan sudah berlangsung. Langsung tanggapi ucapan atau pertanyaan user!"
            . "\n2. JANGAN memaksakan topik yang sama terus-menerus. Fokus 100% pada apa yang baru saja ditanyakan atau diminta user saat ini."
            . "\n3. JANGAN menulis placeholder seperti [User Name] atau tanda kurung siku apapun selain SATU tag emosi di awal kalimat.";

        if (!empty($similarMemories)) {
            $memoriesContext = "\n\n[CATATAN FAKTA TENTANG USER (Gunakan hanya jika relevan dengan obrolan, jangan dipaksakan)]:\n" . collect($similarMemories)
                ->map(fn($m) => "- " . $m->memory_text)
                ->implode("\n");
        }

        $fullSystemPrompt = $setting->system_prompt . $userContext . $memoriesContext;

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

        // 4. Resolve selected model
        $selectedModel = $validated['ai_model'] ?? $setting->ai_model ?? 'qwen2.5:3b';

        // 5. Return SSE StreamedResponse
        return response()->stream(function () use ($chatSession, $user, $validated, $promptPayload, $selectedModel) {
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
                    $rawTag = strtolower(trim($matches[1]));
                    $tagMap = [
                        'happy' => 'happy', 'senang' => 'happy', 'gembira' => 'happy', 'ceria' => 'happy',
                        'blush' => 'blush', 'malu' => 'blush', 'tersipu' => 'blush',
                        'sad' => 'sad', 'sedih' => 'sad', 'kecewa' => 'sad',
                        'surprised' => 'surprised', 'kaget' => 'surprised', 'terkejut' => 'surprised',
                        'thinking' => 'thinking', 'mikir' => 'thinking', 'bingung' => 'thinking',
                        'smug' => 'smug', 'bangga' => 'smug',
                        'neutral' => 'neutral', 'hening' => 'neutral', 'tenang' => 'neutral',
                    ];
                    $emotionExtracted = $tagMap[$rawTag] ?? 'neutral';

                    // Send emotion event to frontend
                    echo "event: emotion\n";
                    echo "data: " . json_encode(['emotion' => $emotionExtracted]) . "\n\n";
                    flush();
                }

                // Send chunk
                echo "event: message\n";
                echo "data: " . json_encode(['chunk' => $token, 'done' => $done]) . "\n\n";
                flush();
            }, $selectedModel);

            // Normalizer helper for emotion tags
            $normalizeEmotion = function ($tag) {
                $t = strtolower(trim($tag ?? ''));
                $map = [
                    'happy' => 'happy', 'senang' => 'happy', 'gembira' => 'happy', 'ceria' => 'happy',
                    'blush' => 'blush', 'malu' => 'blush', 'tersipu' => 'blush', 'love' => 'blush',
                    'sad' => 'sad', 'sedih' => 'sad', 'kecewa' => 'sad',
                    'surprised' => 'surprised', 'kaget' => 'surprised', 'terkejut' => 'surprised',
                    'thinking' => 'thinking', 'mikir' => 'thinking', 'bingung' => 'thinking',
                    'smug' => 'smug', 'bangga' => 'smug',
                    'neutral' => 'neutral', 'hening' => 'neutral', 'tenang' => 'neutral', 'santai' => 'neutral',
                ];
                return $map[$t] ?? 'neutral';
            };

            $cleanContent = $completeResponse;
            $emotion = 'neutral';
            if (preg_match('/^\[(.*?)\]\s*(.*)/s', trim($completeResponse), $m)) {
                $emotion = $normalizeEmotion($m[1]);
                $cleanContent = $m[2];
            }

            // Remove any accidental leftover [User Name] or [Placeholder] brackets from the body text
            $cleanContent = preg_replace('/\[(User Name|User|Nama|Pengguna|Name)\]/i', ($user->name ?? 'kamu'), $cleanContent);

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
