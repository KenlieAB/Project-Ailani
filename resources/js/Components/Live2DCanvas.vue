<template>
  <div class="relative w-full h-full flex items-center justify-center overflow-hidden bg-gradient-to-b from-[#0b0f19] via-[#0d1424] to-[#080c14] select-none rounded-xl">
    <!-- Ambient glowing backdrop effect -->
    <div class="absolute w-72 h-72 bg-purple-600/10 rounded-full blur-3xl pointer-events-none -top-10 left-1/4 animate-pulse"></div>
    <div class="absolute w-64 h-64 bg-indigo-600/10 rounded-full blur-3xl pointer-events-none bottom-4 right-1/4"></div>

    <!-- Live2D Canvas element -->
    <canvas ref="canvasRef" class="w-full h-full block cursor-grab active:cursor-grabbing"></canvas>

    <!-- Loading Overlay -->
    <div v-if="isLoading" class="absolute inset-0 flex flex-col items-center justify-center bg-slate-950/80 backdrop-blur-sm z-10 transition-opacity duration-300">
      <div class="w-10 h-10 border-3 border-purple-400 border-t-transparent rounded-full animate-spin"></div>
      <p class="mt-3 text-xs font-medium text-slate-300 tracking-wide">Memuat Live2D...</p>
    </div>

    <!-- Error Overlay with Retry Button -->
    <div v-if="loadError" class="absolute inset-0 flex flex-col items-center justify-center bg-slate-950/90 backdrop-blur-sm z-10 p-4 text-center">
      <div class="w-10 h-10 rounded-full bg-rose-500/20 text-rose-400 flex items-center justify-center mb-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
      </div>
      <p class="text-xs font-semibold text-rose-300">Gagal Memuat Model</p>
      <p class="text-[11px] text-slate-400 mt-1 max-w-xs">{{ loadError }}</p>
      <button
        @click="retryInit"
        class="mt-3 px-3 py-1.5 bg-purple-600 hover:bg-purple-500 text-white text-[11px] font-medium rounded-lg shadow transition"
      >
        Muat Ulang
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import * as PIXI from 'pixi.js';
import { Live2DModel } from 'pixi-live2d-display/cubism4';

if (typeof window !== 'undefined') {
  window.PIXI = PIXI;
}

try {
  Live2DModel.registerTicker(PIXI.Ticker);
} catch (e) {}

const props = defineProps({
  modelPath: {
    type: String,
    default: '/live2d/models/ailani/ailani.model3.json',
  },
});

const emit = defineEmits(['emotion-changed']);

const canvasRef = ref(null);
const isLoading = ref(true);
const loadError = ref(null);
const currentEmotion = ref('neutral');

let app = null;
let currentModel = null;

const EMOTION_MAP = {
  neutral: { expIndex: 0, motionGroup: 'Idle', motionIndex: 0 },
  happy: { expIndex: 1, motionGroup: 'Tap', motionIndex: 0 },
  joy: { expIndex: 1, motionGroup: 'Tap', motionIndex: 0 },
  blush: { expIndex: 2, motionGroup: 'Idle', motionIndex: 1 },
  sad: { expIndex: 3, motionGroup: 'Idle', motionIndex: 2 },
  surprised: { expIndex: 4, motionGroup: 'Tap', motionIndex: 1 },
  thinking: { expIndex: 5, motionGroup: 'Idle', motionIndex: 0 },
  smug: { expIndex: 6, motionGroup: 'Idle', motionIndex: 1 },
  empathy: { expIndex: 0, motionGroup: 'Idle', motionIndex: 0 },
};

const setEmotion = (tag) => {
  if (!tag) return;
  const normalized = tag.toLowerCase().trim();
  currentEmotion.value = normalized;
  emit('emotion-changed', normalized);

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

  // Scale model nicely inside card
  const scale = (height * 0.92) / currentModel.height;
  currentModel.scale.set(scale);

  currentModel.anchor.set(0.5, 0.5);
  currentModel.x = width / 2;
  currentModel.y = height / 2 + (height * 0.08);
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

  cleanupPixi();
  await waitForCubismCore();

  const parent = canvasRef.value.parentElement;
  const width = parent?.clientWidth || 360;
  const height = parent?.clientHeight || 380;

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

    currentModel = await Live2DModel.from(props.modelPath, {
      autoInteract: false,
    });

    if (!currentModel) {
      throw new Error('Gagal menginisialisasi model Live2D.');
    }

    fitModelToScreen();
    app.stage.addChild(currentModel);

    setEmotion('neutral');

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
  currentEmotion,
});
</script>
