<template>
  <div class="relative w-full h-full flex items-center justify-center overflow-hidden bg-gradient-to-b from-slate-900 via-indigo-950 to-slate-900 select-none">
    <!-- Ambient glowing backdrop effect -->
    <div class="absolute w-96 h-96 bg-pink-500/10 rounded-full blur-3xl pointer-events-none -top-10 left-1/4 animate-pulse"></div>
    <div class="absolute w-80 h-80 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none bottom-10 right-1/4"></div>

    <!-- Live2D Canvas element -->
    <canvas ref="canvasRef" class="w-full h-full block cursor-grab active:cursor-grabbing"></canvas>

    <!-- Loading Overlay -->
    <div v-if="isLoading" class="absolute inset-0 flex flex-col items-center justify-center bg-slate-950/70 backdrop-blur-sm z-10 transition-opacity duration-300">
      <div class="w-12 h-12 border-4 border-indigo-400 border-t-transparent rounded-full animate-spin"></div>
      <p class="mt-4 text-sm font-medium text-slate-300 tracking-wide">Memuat Ailani Live2D...</p>
    </div>

    <!-- Error Overlay with Retry Button -->
    <div v-if="loadError" class="absolute inset-0 flex flex-col items-center justify-center bg-slate-950/80 backdrop-blur-sm z-10 p-6 text-center">
      <div class="w-12 h-12 rounded-full bg-rose-500/20 text-rose-400 flex items-center justify-center mb-3">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
      </div>
      <p class="text-sm font-semibold text-rose-300">Gagal Memuat Model Live2D</p>
      <p class="text-xs text-slate-400 mt-1 max-w-sm">{{ loadError }}</p>
      <button
        @click="retryInit"
        class="mt-4 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-medium rounded-lg shadow transition"
      >
        Coba Muat Ulang
      </button>
    </div>

    <!-- Emotion Badge Indicator (top-left) -->
    <div class="absolute top-4 left-4 z-20 flex items-center space-x-2 bg-slate-900/80 backdrop-blur-md px-3 py-1.5 rounded-full border border-slate-700/60 shadow-lg text-xs font-medium text-slate-200">
      <span class="inline-block w-2.5 h-2.5 rounded-full" :class="emotionDotClass"></span>
      <span class="capitalize">{{ currentEmotion }}</span>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick, computed } from 'vue';
import * as PIXI from 'pixi.js';
import { Live2DModel } from 'pixi-live2d-display/cubism4';

if (typeof window !== 'undefined') {
  window.PIXI = PIXI;
}

try {
  Live2DModel.registerTicker(PIXI.Ticker);
} catch (e) {
  // Ticker registration notice
}

const props = defineProps({
  modelPath: {
    type: String,
    default: '/live2d/models/ailani/ailani.model3.json',
  },
});

const canvasRef = ref(null);
const isLoading = ref(true);
const loadError = ref(null);
const currentEmotion = ref('neutral');

let app = null;
let currentModel = null;

const EMOTION_MAP = {
  neutral: { expIndex: 0, motionGroup: 'Idle', motionIndex: 0 },
  happy: { expIndex: 1, motionGroup: 'Tap', motionIndex: 0 },
  blush: { expIndex: 2, motionGroup: 'Idle', motionIndex: 1 },
  sad: { expIndex: 3, motionGroup: 'Idle', motionIndex: 2 },
  surprised: { expIndex: 4, motionGroup: 'Tap', motionIndex: 1 },
  thinking: { expIndex: 5, motionGroup: 'Idle', motionIndex: 0 },
};

const emotionDotClass = computed(() => {
  switch (currentEmotion.value) {
    case 'happy': return 'bg-emerald-400 animate-pulse';
    case 'blush': return 'bg-pink-400 animate-pulse';
    case 'sad': return 'bg-sky-400';
    case 'surprised': return 'bg-amber-400 animate-ping';
    case 'thinking': return 'bg-purple-400 animate-pulse';
    default: return 'bg-indigo-400';
  }
});

const setEmotion = (tag) => {
  if (!tag) return;
  const normalized = tag.toLowerCase().trim();
  currentEmotion.value = normalized;

  if (!currentModel) return;

  const target = EMOTION_MAP[normalized] || EMOTION_MAP.neutral;

  try {
    if (typeof currentModel.expression === 'function') {
      currentModel.expression(target.expIndex);
    }

    if (target.motionGroup && typeof currentModel.motion === 'function') {
      currentModel.motion(target.motionGroup, target.motionIndex, 2);
    }
  } catch (err) {
    console.warn('Live2D setEmotion notice:', err);
  }
};

const fitModelToScreen = () => {
  if (!currentModel || !app) return;

  const width = app.renderer.width / app.renderer.resolution;
  const height = app.renderer.height / app.renderer.resolution;

  const scale = (height * 0.85) / currentModel.height;
  currentModel.scale.set(scale);

  currentModel.anchor.set(0.5, 0.5);
  currentModel.x = width / 2;
  currentModel.y = height / 2 + (height * 0.05);
};

const handlePointerMove = (e) => {
  if (!currentModel || !canvasRef.value) return;
  try {
    const rect = canvasRef.value.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    if (typeof currentModel.focus === 'function') {
      currentModel.focus(x, y);
    }
  } catch (err) {}
};

/**
 * Ensure Live2D Cubism Core runtime script is ready before loading model
 */
const waitForCubismCore = async () => {
  let attempts = 0;
  while ((typeof window === 'undefined' || !window.Live2DCubismCore) && attempts < 50) {
    await new Promise((resolve) => setTimeout(resolve, 80));
    attempts++;
  }
};

const cleanupPixi = () => {
  if (canvasRef.value) {
    canvasRef.value.removeEventListener('pointermove', handlePointerMove);
  }
  if (currentModel) {
    try {
      currentModel.destroy();
    } catch (e) {}
    currentModel = null;
  }
  if (app) {
    try {
      app.destroy(true, { children: true, texture: true, baseTexture: true });
    } catch (e) {}
    app = null;
  }
};

const initPixi = async () => {
  await nextTick();
  if (!canvasRef.value) return;

  isLoading.value = true;
  loadError.value = null;

  // Clean previous instance if existing
  cleanupPixi();

  // Wait for Cubism Core if browser is still evaluating scripts
  await waitForCubismCore();

  const parent = canvasRef.value.parentElement;
  const width = parent?.clientWidth || window.innerWidth * 0.6 || 800;
  const height = parent?.clientHeight || window.innerHeight || 800;

  try {
    app = new PIXI.Application({
      view: canvasRef.value,
      width: width,
      height: height,
      autoDensity: true,
      resolution: window.devicePixelRatio || 1,
      backgroundAlpha: 0,
      antialias: true,
    });

    // Load Cubism 4 model safely
    currentModel = await Live2DModel.from(props.modelPath, {
      autoInteract: false,
    });

    if (!currentModel) {
      throw new Error('Gagal menginisialisasi model Live2D.');
    }

    fitModelToScreen();
    app.stage.addChild(currentModel);

    // Initial neutral expression
    setEmotion('neutral');

    // Pointer events
    canvasRef.value.addEventListener('pointermove', handlePointerMove);
    canvasRef.value.addEventListener('pointerdown', () => {
      setEmotion('happy');
    });

  } catch (err) {
    console.error('Failed to load Live2D model:', err);
    loadError.value = err?.message || 'Error saat memuat file model Live2D.';
  } finally {
    isLoading.value = false;
  }
};

const retryInit = () => {
  initPixi();
};

const handleResize = () => {
  if (!app || !canvasRef.value) return;
  const parent = canvasRef.value.parentElement;
  if (!parent) return;

  const width = parent.clientWidth;
  const height = parent.clientHeight;

  app.renderer.resize(width, height);
  fitModelToScreen();
};

onMounted(() => {
  initPixi();
  window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
  window.removeEventListener('resize', handleResize);
  cleanupPixi();
});

defineExpose({
  setEmotion,
});
</script>
