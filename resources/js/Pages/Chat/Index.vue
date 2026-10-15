<template>
  <div class="flex flex-col h-screen w-screen bg-[#070a11] text-slate-100 overflow-hidden font-sans select-none">
    
    <!-- 1. TOP WINDOW NAVBAR (Mac style header with title & status) -->
    <header class="h-13 min-h-[52px] bg-[#0b0f19] border-b border-slate-800/80 px-4 flex items-center justify-between z-20 shrink-0">
      <!-- Left: Mac Window Controls + App Title -->
      <div class="flex items-center space-x-3.5">
        <div class="flex items-center space-x-2">
          <span class="w-3 h-3 rounded-full bg-[#ff5f56] inline-block"></span>
          <span class="w-3 h-3 rounded-full bg-[#ffbd2e] inline-block"></span>
          <span class="w-3 h-3 rounded-full bg-[#27c93f] inline-block"></span>
        </div>

        <div class="flex items-center space-x-2 pl-2">
          <span class="w-2.5 h-2.5 rounded-full bg-purple-500 inline-block shadow-sm shadow-purple-500/50"></span>
          <span class="text-sm font-bold tracking-tight text-white">{{ companion.companion_name }} Companion</span>
          <span class="text-xs text-slate-400 font-medium">Live2D · Local</span>
        </div>
      </div>

      <!-- Center: Privacy / Security Badge -->
      <div class="hidden sm:flex items-center space-x-2 px-3.5 py-1 bg-emerald-950/30 border border-emerald-500/40 rounded-full text-xs font-medium text-emerald-400 shadow-sm shadow-emerald-950/40">
        <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
        <span>100% On-Device · 0 Bytes Outbound</span>
      </div>

      <!-- Right: Action Buttons -->
      <div class="flex items-center space-x-2">
        <button
          @click="isSidebarOpen = !isSidebarOpen"
          class="flex items-center space-x-1.5 text-xs bg-[#131b2e] hover:bg-[#1a253f] border border-slate-700/70 text-slate-200 px-3 py-1.5 rounded-lg transition font-medium"
          title="Riwayat Sesi Chat"
        >
          <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
          <span class="hidden md:inline">Sesi</span>
        </button>

        <!-- Active Model Badge -->
        <div class="hidden sm:flex items-center space-x-1.5 bg-[#0e1322] border border-slate-700/60 rounded-xl px-2.5 py-1 text-[11px] text-slate-300">
          <span class="w-1.5 h-1.5 rounded-full bg-purple-400"></span>
          <span class="font-medium text-slate-300">Qwen 2.5 (3B)</span>
        </div>

        <button
          @click="startNewChat"
          class="flex items-center space-x-1.5 text-xs bg-[#131b2e] hover:bg-[#1a253f] border border-slate-700/70 text-slate-200 px-3 py-1.5 rounded-lg transition font-medium"
        >
          <svg class="w-3.5 h-3.5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          <span>New Chat</span>
        </button>

        <Link
          :href="route('logout')"
          method="post"
          as="button"
          class="p-1.5 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-[#1a253f] transition"
          title="Keluar"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
          </svg>
        </Link>
      </div>
    </header>

    <!-- 2. MAIN LAYOUT: Split Left & Right -->
    <div class="flex-1 flex overflow-hidden relative">

      <!-- LEFT PANE: Avatar, Live2D card & Quick Interactions -->
      <aside class="hidden lg:flex flex-col w-[370px] xl:w-[410px] bg-[#090d16] border-r border-slate-800/80 p-4 space-y-3.5 overflow-y-auto shrink-0 select-none">
        
        <!-- Companion Header Row -->
        <div class="flex items-center justify-between px-1">
          <div class="flex items-center space-x-3">
            <div class="relative">
              <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-purple-600 to-indigo-500 p-0.5 shadow-md shadow-purple-900/40">
                <div class="w-full h-full rounded-full bg-slate-900 flex items-center justify-center overflow-hidden">
                  <span class="text-base font-bold text-purple-300">🌸</span>
                </div>
              </div>
              <div class="w-2.5 h-2.5 rounded-full bg-emerald-400 absolute bottom-0 right-0 border-2 border-slate-900"></div>
            </div>
            <div>
              <h2 class="text-sm font-bold text-white tracking-wide">{{ companion.companion_name }}</h2>
              <p class="text-xs text-slate-400">Live2D Anime Companion</p>
            </div>
          </div>

          <div class="flex items-center space-x-1.5 px-2.5 py-1 rounded-full border border-emerald-500/40 bg-emerald-950/20 text-emerald-400 text-xs font-medium">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
            <span>Offline</span>
          </div>
        </div>

        <!-- Live2D Card Container (Expanded to fill vertical space) -->
        <div class="flex-1 flex flex-col bg-[#0d1322] border border-slate-800/90 rounded-2xl p-3 shadow-xl relative overflow-hidden min-h-0">
          
          <!-- Card Top Bar: Live2D Status & Tracking -->
          <div class="flex items-center justify-between px-2 py-1.5 border-b border-slate-800/60 mb-2 text-xs shrink-0">
            <div class="flex items-center space-x-2">
              <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
              <span class="font-medium text-slate-300">Live2D: {{ companion.companion_name }}</span>
              <span class="text-purple-400 font-semibold uppercase tracking-wider text-[11px]">
                · [{{ activeEmotion }}]
              </span>
            </div>

            <div class="flex items-center space-x-1 text-[11px] text-slate-400 bg-slate-900/70 border border-slate-800 px-2 py-0.5 rounded-md">
              <span class="text-slate-500">Tracking:</span>
              <span class="text-emerald-400 font-medium">ON</span>
            </div>
          </div>

          <!-- Canvas Viewport (Expands vertically) -->
          <div class="relative flex-1 w-full min-h-[380px] rounded-xl overflow-hidden bg-gradient-to-b from-[#090d17] to-[#0d1424]">
            <Live2DCanvas
              ref="live2dRef"
              :model-path="companion.live2d_model_path"
              @emotion-changed="onEmotionChanged"
              class="w-full h-full"
            />
          </div>

          <!-- Quick Emotion Chips below Canvas inside Card -->
          <div class="flex items-center justify-center flex-wrap gap-1.5 pt-3 px-1 shrink-0">
            <button
              v-for="chip in quickChips"
              :key="chip"
              @click="triggerEmotion(chip)"
              class="px-2.5 py-1 rounded-lg text-[11px] font-medium transition cursor-pointer"
              :class="activeEmotion.toLowerCase() === chip.toLowerCase() 
                ? 'bg-purple-600/40 text-purple-200 border border-purple-500/60 shadow-sm' 
                : 'bg-slate-900/80 text-slate-400 border border-slate-800/80 hover:text-slate-200 hover:bg-slate-800'"
            >
              {{ chip }}
            </button>
          </div>
        </div>

      </aside>

      <!-- RIGHT PANE: Chat Messages Stream & Input Box -->
      <main class="flex-1 flex flex-col h-full bg-[#080c14] overflow-hidden relative">

        <!-- History Drawer Backdrop & Drawer -->
        <transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="-translate-x-full opacity-0"
          enter-to-class="translate-x-0 opacity-100"
          leave-active-class="transition duration-150 ease-in"
          leave-from-class="translate-x-0 opacity-100"
          leave-to-class="-translate-x-full opacity-0"
        >
          <div
            v-if="isSidebarOpen"
            class="absolute inset-y-0 left-0 w-80 bg-[#0d1322]/98 backdrop-blur-2xl border-r border-slate-800 z-30 flex flex-col shadow-2xl"
          >
            <div class="p-4 border-b border-slate-800/80 flex items-center justify-between">
              <h3 class="text-sm font-semibold text-slate-200">Riwayat Obrolan</h3>
              <button
                @click="isSidebarOpen = false"
                class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <div class="flex-1 overflow-y-auto p-3 space-y-1.5">
              <div
                v-for="s in sessions"
                :key="s.id"
                class="group flex items-center justify-between px-3 py-2.5 rounded-xl transition cursor-pointer text-sm"
                :class="s.id === activeSession?.id ? 'bg-purple-600/30 text-purple-200 border border-purple-500/40' : 'text-slate-400 hover:bg-slate-800/60 hover:text-slate-200'"
                @click="selectSession(s.id)"
              >
                <span class="truncate flex-1 pr-2">{{ s.title }}</span>
                <button
                  @click.stop="deleteSession(s.id)"
                  class="opacity-0 group-hover:opacity-100 text-slate-500 hover:text-rose-400 transition p-1"
                  title="Hapus"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </transition>

        <!-- Messages Container -->
        <div
          ref="chatContainerRef"
          class="flex-1 overflow-y-auto px-4 md:px-8 py-6 space-y-6 scroll-smooth"
        >
          <!-- Welcome empty state -->
          <div v-if="chatMessages.length === 0" class="flex flex-col items-center justify-center h-full text-center text-slate-500 py-16">
            <div class="w-16 h-16 rounded-2xl bg-purple-500/10 flex items-center justify-center text-purple-400 mb-4 border border-purple-500/20 shadow-inner">
              <span class="text-2xl">🌸</span>
            </div>
            <h3 class="text-base font-semibold text-slate-200">Yaho~! Aku {{ companion.companion_name }}</h3>
            <p class="text-sm max-w-sm mt-1 text-slate-400">Teman Live2D lokal kamu. 100% aman di komputermu tanpa data yang keluar. Ada yang bisa kubantu?</p>
          </div>

          <!-- Message List -->
          <div
            v-for="(msg, index) in chatMessages"
            :key="msg.id || index"
            class="flex flex-col space-y-1"
          >
            <!-- A. COMPANION MESSAGE -->
            <div v-if="msg.sender === 'companion'" class="flex items-start space-x-3 max-w-[88%] lg:max-w-[82%]">
              <!-- Companion Circle Avatar -->
              <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-purple-600 to-indigo-500 p-0.5 shrink-0 mt-0.5 shadow-md">
                <div class="w-full h-full rounded-full bg-slate-900 flex items-center justify-center">
                  <span class="text-xs">🌸</span>
                </div>
              </div>

              <!-- Companion Content -->
              <div class="flex-1 flex flex-col space-y-1.5">
                <!-- Header Meta: Name [Emotion] · Time -->
                <div class="flex items-center space-x-2 text-xs">
                  <span class="font-bold text-slate-200">{{ companion.companion_name }}</span>
                  <span class="font-semibold text-sky-400 uppercase tracking-wide text-[11px]">
                    [{{ msg.emotion_tag || 'Neutral' }}]
                  </span>
                  <span class="text-slate-500 text-[11px]">· {{ formatTime(msg.created_at) }}</span>
                </div>

                <!-- Chat Bubble Card -->
                <div class="bg-[#101726] border border-slate-800/80 rounded-2xl rounded-tl-sm px-4 py-3.5 text-slate-200 text-sm leading-relaxed shadow-lg whitespace-pre-wrap word-break">
                  {{ msg.clean_content || msg.content }}
                </div>

                <!-- Sub-meta / Copy toolbar -->
                <div class="flex items-center space-x-3 text-[11px] text-slate-500 pl-1">
                  <span>Local Ollama · 0 Outbound</span>
                  <button
                    @click="copyText(msg.clean_content || msg.content)"
                    class="hover:text-slate-300 transition"
                    title="Salin Pesan"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>

            <!-- B. USER MESSAGE -->
            <div v-else class="flex flex-col items-end self-end max-w-[85%] space-y-1">
              <!-- Header Meta: You · Time -->
              <div class="flex items-center space-x-1.5 text-[11px] text-slate-400 pr-1">
                <span class="font-medium text-slate-300">You</span>
                <span>· {{ formatTime(msg.created_at) }}</span>
              </div>

              <!-- User Purple Bubble -->
              <div class="bg-[#9333ea] hover:bg-[#8b2fe0] text-white rounded-2xl rounded-tr-sm px-4 py-2.5 text-sm leading-relaxed shadow-md shadow-purple-950/40 whitespace-pre-wrap word-break transition">
                {{ msg.content }}
              </div>
            </div>
          </div>

          <!-- Active Streaming Companion Bubble -->
          <div v-if="isStreaming" class="flex items-start space-x-3 max-w-[88%] lg:max-w-[82%]">
            <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-purple-600 to-indigo-500 p-0.5 shrink-0 mt-0.5 shadow-md animate-pulse">
              <div class="w-full h-full rounded-full bg-slate-900 flex items-center justify-center">
                <span class="text-xs">🌸</span>
              </div>
            </div>

            <div class="flex-1 flex flex-col space-y-1.5">
              <div class="flex items-center space-x-2 text-xs">
                <span class="font-bold text-slate-200">{{ companion.companion_name }}</span>
                <span class="font-semibold text-purple-400 uppercase tracking-wide text-[11px]">
                  [{{ currentStreamEmotion || 'Thinking' }}]
                </span>
                <span class="text-slate-500 text-[11px]">· Sekarang</span>
              </div>

              <div class="bg-[#101726] border border-slate-800/80 rounded-2xl rounded-tl-sm px-4 py-3.5 text-slate-200 text-sm leading-relaxed shadow-lg whitespace-pre-wrap">
                <span>{{ streamingCleanText }}</span>
                <span class="inline-block w-1.5 h-4 ml-1 bg-purple-400 animate-pulse align-middle"></span>
              </div>
            </div>
          </div>

        </div>

        <!-- 3. INPUT CARD CONTAINER (Matching Screenshot) -->
        <div class="p-4 md:px-8 pb-5 bg-gradient-to-t from-[#070a11] via-[#070a11] to-transparent">
          <div class="bg-[#0e1322] border border-slate-800/90 rounded-2xl p-3 shadow-2xl flex flex-col space-y-2">
            
            <!-- Textarea -->
            <textarea
              v-model="inputMessage"
              @keydown.enter.exact.prevent="sendMessage"
              rows="2"
              placeholder="Message Ailani... (Watch Live2D expression react)"
              :disabled="isStreaming"
              class="w-full resize-none bg-transparent border-0 px-2 py-1 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-0 leading-relaxed disabled:opacity-50"
            ></textarea>

            <!-- Bottom Toolbar inside input card -->
            <div class="flex items-center justify-between pt-1 border-t border-slate-800/60 text-xs">
              <!-- Left: Zero Cloud Outbound icon & tag -->
              <div class="flex items-center space-x-2 text-slate-400 text-[11px] px-1">
                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                </svg>
                <span class="font-medium text-slate-400">Zero Cloud Outbound</span>
              </div>

              <!-- Right: Enter to send hint + Purple Send button -->
              <div class="flex items-center space-x-3">
                <span class="text-[11px] text-slate-500 hidden sm:inline">Enter to send</span>
                <button
                  @click="sendMessage"
                  :disabled="!inputMessage.trim() || isStreaming"
                  class="bg-[#9333ea] hover:bg-[#8b2fe0] disabled:bg-slate-800 disabled:text-slate-600 text-white font-medium rounded-xl px-4 py-2 text-xs flex items-center space-x-1.5 shadow-md shadow-purple-950/40 transition cursor-pointer"
                >
                  <span v-if="!isStreaming">Send</span>
                  <span v-else>Thinking...</span>
                  <svg v-if="!isStreaming" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                  </svg>
                  <div v-else class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                </button>
              </div>
            </div>

          </div>
        </div>

      </main>

    </div>

  </div>
</template>

<script setup>
import { ref, computed, nextTick, onMounted, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import Live2DCanvas from '@/Components/Live2DCanvas.vue';

const props = defineProps({
  companion: {
    type: Object,
    required: true,
  },
  activeSession: {
    type: Object,
    required: true,
  },
  sessions: {
    type: Array,
    default: () => [],
  },
  messages: {
    type: Array,
    default: () => [],
  },
  availableModels: {
    type: Array,
    default: () => [],
  },
});

const live2dRef = ref(null);
const chatContainerRef = ref(null);
const inputMessage = ref('');
const isSidebarOpen = ref(false);
const isStreaming = ref(false);
const activeEmotion = ref('Neutral');
const selectedModel = ref(props.companion.ai_model || 'qwen2.5:3b');

const switchModel = async (modelId) => {
  if (selectedModel.value === modelId || isStreaming.value) return;
  selectedModel.value = modelId;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  try {
    await fetch(route('chat.model.update'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken || '' },
      body: JSON.stringify({ ai_model: modelId }),
    });
  } catch (e) {
    console.warn('Model switch failed:', e);
  }
};

const quickChips = ['Thinking', 'Empathy', 'Surprised', 'Smug', 'Blush', 'Neutral'];

const chatMessages = ref([...props.messages]);
const streamingRawText = ref('');
const currentStreamEmotion = ref(null);

const onEmotionChanged = (emotion) => {
  activeEmotion.value = emotion.charAt(0).toUpperCase() + emotion.slice(1);
};

const triggerEmotion = (emotion) => {
  const norm = emotion.toLowerCase();
  activeEmotion.value = emotion;
  if (live2dRef.value?.setEmotion) {
    live2dRef.value.setEmotion(norm);
  }
};

const streamingCleanText = computed(() => {
  return streamingRawText.value.replace(/^\[(.*?)\]\s*/s, '');
});

const formatTime = (dateStr) => {
  if (!dateStr) return '';
  const date = new Date(dateStr);
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true });
};

const formatMessages = () => {
  const emotionAlias = {
    hening: 'Neutral', tenang: 'Neutral', santai: 'Neutral',
    senang: 'Happy', gembira: 'Happy', ceria: 'Happy',
    malu: 'Blush', tersipu: 'Blush',
    sedih: 'Sad', kecewa: 'Sad',
    kaget: 'Surprised', terkejut: 'Surprised',
    mikir: 'Thinking', bingung: 'Thinking',
    bangga: 'Smug',
  };

  chatMessages.value = props.messages.map(m => {
    let clean = m.content || '';
    let emotion = m.emotion_tag;
    if (m.sender === 'companion') {
      const match = clean.match(/^\[(.*?)\]\s*(.*)/s);
      if (match) {
        emotion = emotion || match[1].toLowerCase();
        clean = match[2];
      }
    }

    // Clean leftover [User Name] or similar placeholders
    clean = clean.replace(/\[(User Name|User|Nama|Pengguna|Name)\]/gi, 'kamu');

    const normEmotion = emotion ? emotion.toLowerCase().trim() : 'neutral';
    const finalEmotion = emotionAlias[normEmotion] || (normEmotion.charAt(0).toUpperCase() + normEmotion.slice(1));

    return {
      ...m,
      emotion_tag: finalEmotion,
      clean_content: clean,
    };
  });
};

formatMessages();

// Re-format messages whenever the active session or messages prop changes
watch(
  () => [props.activeSession?.id, props.messages],
  () => {
    formatMessages();
    triggerEmotion('neutral');
    scrollToBottom();
  },
  { deep: true }
);

const copyText = (text) => {
  if (!text) return;
  navigator.clipboard.writeText(text);
};

const scrollToBottom = async () => {
  await nextTick();
  if (chatContainerRef.value) {
    chatContainerRef.value.scrollTop = chatContainerRef.value.scrollHeight;
  }
};

onMounted(() => {
  scrollToBottom();
});

const startNewChat = () => {
  isSidebarOpen.value = false;
  inputMessage.value = '';
  router.post(route('chat.session.new'), {}, {
    preserveState: false,
    preserveScroll: false,
  });
};

const selectSession = (sessionId) => {
  isSidebarOpen.value = false;
  router.get(route('chat.index', { session_id: sessionId }), {}, {
    preserveState: false,
    preserveScroll: false,
  });
};

const deleteSession = (sessionId) => {
  if (confirm('Hapus obrolan ini?')) {
    router.delete(route('chat.session.delete', { session: sessionId }), {
      preserveState: false,
      preserveScroll: false,
    });
  }
};

const sendMessage = async () => {
  const content = inputMessage.value.trim();
  if (!content || isStreaming.value) return;

  chatMessages.value.push({
    sender: 'user',
    content: content,
    created_at: new Date().toISOString(),
  });

  inputMessage.value = '';
  isStreaming.value = true;
  streamingRawText.value = '';
  currentStreamEmotion.value = null;

  triggerEmotion('thinking');
  scrollToBottom();

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  try {
    const response = await fetch(route('chat.send'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'text/event-stream',
        'X-CSRF-TOKEN': csrfToken || '',
      },
      body: JSON.stringify({
        chat_session_id: props.activeSession.id,
        content: content,
        ai_model: selectedModel.value,
      }),
    });

    if (!response.ok) {
      throw new Error(`Server returned status ${response.status}`);
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder('utf-8');
    let buffer = '';

    while (true) {
      const { done, value } = await reader.read();
      if (done) break;

      buffer += decoder.decode(value, { stream: true });
      const lines = buffer.split('\n\n');
      buffer = lines.pop();

      for (const block of lines) {
        if (!block.trim()) continue;

        let eventType = 'message';
        let dataPayload = '';

        const eventLines = block.split('\n');
        for (const line of eventLines) {
          if (line.startsWith('event: ')) {
            eventType = line.replace('event: ', '').trim();
          } else if (line.startsWith('data: ')) {
            dataPayload = line.replace('data: ', '').trim();
          }
        }

        if (!dataPayload) continue;

        try {
          const parsed = JSON.parse(dataPayload);

          if (eventType === 'emotion') {
            currentStreamEmotion.value = parsed.emotion;
            triggerEmotion(parsed.emotion);
          } else if (eventType === 'message') {
            streamingRawText.value += parsed.chunk;

            if (!currentStreamEmotion.value) {
              const match = streamingRawText.value.match(/^\[(.*?)\]/);
              if (match) {
                const emotionTag = match[1].toLowerCase().trim();
                currentStreamEmotion.value = emotionTag;
                triggerEmotion(emotionTag);
              }
            }

            scrollToBottom();
          } else if (eventType === 'done') {
            const finalEmotion = parsed.emotion || currentStreamEmotion.value || 'neutral';
            chatMessages.value.push({
              id: parsed.message_id,
              sender: 'companion',
              emotion_tag: finalEmotion.charAt(0).toUpperCase() + finalEmotion.slice(1),
              content: streamingRawText.value,
              clean_content: parsed.clean_content || streamingCleanText.value,
              created_at: new Date().toISOString(),
            });

            streamingRawText.value = '';
            currentStreamEmotion.value = null;
          }
        } catch (e) {
          console.warn('SSE parsing issue:', e);
        }
      }
    }
  } catch (error) {
    console.error('Chat error:', error);
    chatMessages.value.push({
      sender: 'companion',
      emotion_tag: 'Sad',
      content: 'Maaf, terjadi kendala saat menghubungi AI lokal. Pastikan Ollama berjalan di latar belakang!',
      clean_content: 'Maaf, terjadi kendala saat menghubungi AI lokal. Pastikan Ollama berjalan di latar belakang!',
    });
    triggerEmotion('sad');
  } finally {
    isStreaming.value = false;
    scrollToBottom();
  }
};
</script>
