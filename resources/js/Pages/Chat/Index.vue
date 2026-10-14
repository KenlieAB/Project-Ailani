<template>
  <div class="flex h-screen w-screen bg-slate-950 text-slate-100 overflow-hidden font-sans">
    
    <!-- LEFT PANE: Live2D Avatar Canvas (60% on desktop) -->
    <div class="relative hidden md:flex md:w-3/5 h-full flex-col bg-slate-900 border-r border-slate-800/80">
      <!-- Live2D Interactive Canvas -->
      <Live2DCanvas
        ref="live2dRef"
        :model-path="companion.live2d_model_path"
        class="w-full h-full"
      />

      <!-- Overlay Status & Companion Info -->
      <div class="absolute bottom-6 left-6 z-20 flex items-center space-x-3 bg-slate-900/85 backdrop-blur-md px-4 py-2.5 rounded-2xl border border-slate-700/60 shadow-2xl">
        <div class="relative">
          <div class="w-3 h-3 bg-emerald-500 rounded-full animate-ping absolute"></div>
          <div class="w-3 h-3 bg-emerald-400 rounded-full"></div>
        </div>
        <div>
          <h2 class="text-sm font-semibold tracking-wide text-slate-200">{{ companion.companion_name }}</h2>
          <p class="text-xs text-slate-400">Teman AI Lokal Kamu</p>
        </div>
      </div>
    </div>

    <!-- RIGHT PANE: Chat Interface (40% on desktop, 100% on mobile) -->
    <div class="flex-1 md:w-2/5 flex flex-col h-full bg-slate-950 relative">

      <!-- Top Navigation Bar -->
      <header class="h-16 border-b border-slate-800/80 px-4 flex items-center justify-between bg-slate-900/40 backdrop-blur-md z-10">
        <div class="flex items-center space-x-3">
          <button
            @click="isSidebarOpen = !isSidebarOpen"
            class="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition"
            title="Daftar Obrolan"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
          <div>
            <h1 class="text-sm font-semibold text-slate-200 truncate max-w-[200px]">
              {{ activeSession?.title || 'Obrolan' }}
            </h1>
            <span class="text-xs text-indigo-400 flex items-center space-x-1">
              <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 inline-block"></span>
              <span>Lokal (Ollama)</span>
            </span>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center space-x-2">
          <button
            @click="startNewChat"
            class="flex items-center space-x-1 text-xs bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 rounded-lg shadow font-medium transition"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            <span>Baru</span>
          </button>

          <Link
            :href="route('logout')"
            method="post"
            as="button"
            class="p-2 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-slate-800 transition"
            title="Keluar"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
          </Link>
        </div>
      </header>

      <!-- Sidebar Drawer: Chat History Sessions -->
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
          class="absolute inset-y-0 left-0 w-72 bg-slate-900/95 backdrop-blur-xl border-r border-slate-800 z-30 flex flex-col shadow-2xl"
        >
          <div class="p-4 border-b border-slate-800 flex items-center justify-between">
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

          <div class="flex-1 overflow-y-auto p-3 space-y-1">
            <div
              v-for="s in sessions"
              :key="s.id"
              class="group flex items-center justify-between px-3 py-2.5 rounded-xl transition cursor-pointer text-sm"
              :class="s.id === activeSession?.id ? 'bg-indigo-600/30 text-indigo-200 border border-indigo-500/30' : 'text-slate-400 hover:bg-slate-800/60 hover:text-slate-200'"
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

      <!-- Chat Messages Scrollable Container -->
      <div
        ref="chatContainerRef"
        class="flex-1 overflow-y-auto px-4 py-6 space-y-4 scroll-smooth"
      >
        <!-- Welcome empty state -->
        <div v-if="chatMessages.length === 0" class="flex flex-col items-center justify-center h-full text-center text-slate-500 py-12">
          <div class="w-16 h-16 rounded-2xl bg-indigo-500/10 flex items-center justify-center text-indigo-400 mb-4 border border-indigo-500/20">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
            </svg>
          </div>
          <h3 class="text-base font-semibold text-slate-300">Halo! Aku {{ companion.companion_name }}</h3>
          <p class="text-sm max-w-xs mt-1 text-slate-400">Ceritakan apa saja padaku hari ini. Aku siap mendengarkan!</p>
        </div>

        <!-- Render Message List -->
        <div
          v-for="(msg, index) in chatMessages"
          :key="msg.id || index"
          class="flex flex-col"
          :class="msg.sender === 'user' ? 'items-end' : 'items-start'"
        >
          <!-- Companion Emotion Label (if applicable) -->
          <div
            v-if="msg.sender === 'companion' && msg.emotion_tag"
            class="text-[10px] font-semibold text-indigo-400 mb-1 ml-2 tracking-wider uppercase flex items-center space-x-1"
          >
            <span>[{{ msg.emotion_tag }}]</span>
          </div>

          <!-- Message Bubble -->
          <div
            class="max-w-[85%] rounded-2xl px-4 py-3 text-sm leading-relaxed shadow-md whitespace-pre-wrap word-break transition-all"
            :class="msg.sender === 'user' 
              ? 'bg-indigo-600 text-white rounded-br-none' 
              : 'bg-slate-800/90 text-slate-200 border border-slate-700/60 rounded-bl-none shadow-indigo-950/20'"
          >
            {{ msg.clean_content || msg.content }}
          </div>
        </div>

        <!-- Streaming Assistant Message Bubble (Active response) -->
        <div v-if="isStreaming" class="flex flex-col items-start">
          <div v-if="currentStreamEmotion" class="text-[10px] font-semibold text-indigo-400 mb-1 ml-2 tracking-wider uppercase">
            <span>[{{ currentStreamEmotion }}]</span>
          </div>
          <div class="max-w-[85%] rounded-2xl rounded-bl-none px-4 py-3 text-sm leading-relaxed bg-slate-800/90 text-slate-200 border border-slate-700/60 shadow-md whitespace-pre-wrap">
            <span>{{ streamingCleanText }}</span>
            <span class="inline-block w-1.5 h-4 ml-1 bg-indigo-400 animate-pulse align-middle"></span>
          </div>
        </div>
      </div>

      <!-- Input Field Area -->
      <div class="p-4 bg-slate-900/60 border-t border-slate-800/80 backdrop-blur-md">
        <form @submit.prevent="sendMessage" class="flex items-end space-x-2">
          <div class="flex-1 relative">
            <textarea
              v-model="inputMessage"
              @keydown.enter.exact.prevent="sendMessage"
              rows="1"
              placeholder="Ketik pesan untuk Ailani..."
              :disabled="isStreaming"
              class="w-full resize-none rounded-xl bg-slate-800/80 border border-slate-700/70 px-4 py-3 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition disabled:opacity-50"
            ></textarea>
          </div>

          <button
            type="submit"
            :disabled="!inputMessage.trim() || isStreaming"
            class="h-11 px-4 bg-indigo-600 hover:bg-indigo-500 disabled:bg-slate-800 disabled:text-slate-600 text-white font-medium rounded-xl shadow-lg transition flex items-center justify-center shrink-0"
          >
            <svg v-if="!isStreaming" class="w-5 h-5 rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
            </svg>
            <div v-else class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
          </button>
        </form>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, onMounted } from 'vue';
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
});

const live2dRef = ref(null);
const chatContainerRef = ref(null);
const inputMessage = ref('');
const isSidebarOpen = ref(false);
const isStreaming = ref(false);

const chatMessages = ref([...props.messages]);
const streamingRawText = ref('');
const currentStreamEmotion = ref(null);

// Strip [emotion] from beginning of raw string
const streamingCleanText = computed(() => {
  return streamingRawText.value.replace(/^\[(.*?)\]\s*/s, '');
});

// Format initial messages clean content
const formatMessages = () => {
  chatMessages.value = props.messages.map(m => {
    let clean = m.content;
    let emotion = m.emotion_tag;
    if (m.sender === 'companion') {
      const match = m.content.match(/^\[(.*?)\]\s*(.*)/s);
      if (match) {
        emotion = emotion || match[1].toLowerCase();
        clean = match[2];
      }
    }
    return {
      ...m,
      emotion_tag: emotion,
      clean_content: clean,
    };
  });
};

formatMessages();

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
  router.post(route('chat.session.new'));
};

const selectSession = (sessionId) => {
  isSidebarOpen.value = false;
  router.get(route('chat.index', { session_id: sessionId }));
};

const deleteSession = (sessionId) => {
  if (confirm('Hapus obrolan ini?')) {
    router.delete(route('chat.session.delete', { session: sessionId }));
  }
};

/**
 * Handle SSE Stream Reader from /chat/send
 */
const sendMessage = async () => {
  const content = inputMessage.value.trim();
  if (!content || isStreaming.value) return;

  // Add user message to UI immediately
  chatMessages.value.push({
    sender: 'user',
    content: content,
    created_at: new Date().toISOString(),
  });

  inputMessage.value = '';
  isStreaming.value = true;
  streamingRawText.value = '';
  currentStreamEmotion.value = null;

  // Trigger companion thinking emotion during query
  if (live2dRef.value?.setEmotion) {
    live2dRef.value.setEmotion('thinking');
  }

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
      buffer = lines.pop(); // Retain incomplete fragment

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
            if (live2dRef.value?.setEmotion) {
              live2dRef.value.setEmotion(parsed.emotion);
            }
          } else if (eventType === 'message') {
            streamingRawText.value += parsed.chunk;

            // Intercept first chunk using Regex /^\[(.*?)\]/ to extract emotion tag
            if (!currentStreamEmotion.value) {
              const match = streamingRawText.value.match(/^\[(.*?)\]/);
              if (match) {
                const emotionTag = match[1].toLowerCase().trim();
                currentStreamEmotion.value = emotionTag;
                if (live2dRef.value?.setEmotion) {
                  live2dRef.value.setEmotion(emotionTag);
                }
              }
            }

            scrollToBottom();
          } else if (eventType === 'done') {
            chatMessages.value.push({
              id: parsed.message_id,
              sender: 'companion',
              emotion_tag: parsed.emotion || currentStreamEmotion.value,
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
      emotion_tag: 'sad',
      content: 'Maaf, terjadi kendala saat menghubungi AI lokal. Pastikan Ollama berjalan di latar belakang!',
      clean_content: 'Maaf, terjadi kendala saat menghubungi AI lokal. Pastikan Ollama berjalan di latar belakang!',
    });
    if (live2dRef.value?.setEmotion) {
      live2dRef.value.setEmotion('sad');
    }
  } finally {
    isStreaming.value = false;
    scrollToBottom();
  }
};
</script>
