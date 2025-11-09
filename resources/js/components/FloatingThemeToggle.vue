<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/composables/useAppearance';
import { Moon, Sun } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref } from 'vue';

const { updateAppearance } = useAppearance();

// Track current applied theme (dark class on <html>) so icon reflects actual state,
// including when appearance = 'system'.
const isDark = ref(false);
const updateIsDark = () => {
  if (typeof document !== 'undefined') {
    isDark.value = document.documentElement.classList.contains('dark');
  }
};

onMounted(() => {
  updateIsDark();
  const mql = typeof window !== 'undefined' ? window.matchMedia('(prefers-color-scheme: dark)') : null;
  const handler = () => updateIsDark();
  if (mql) {
    mql.addEventListener('change', handler);
  }
  (isDark as any)._mql = mql;
  (isDark as any)._handler = handler;
});

onUnmounted(() => {
  const mql = (isDark as any)._mql as MediaQueryList | null;
  const handler = (isDark as any)._handler as (() => void) | null;
  if (mql && handler) {
    mql.removeEventListener('change', handler as EventListener);
  }
});

const getIcon = () => (isDark.value ? Moon : Sun);

const toggleLightDark = () => {
  const next = isDark.value ? 'light' : 'dark';
  updateAppearance(next);
  // update local state immediately to reflect the new icon
  isDark.value = next === 'dark';
};
</script>

<template>
  <Button
    variant="outline"
    size="icon"
    class="fixed bottom-6 right-6 z-50 h-12 w-12 rounded-full border border-neutral-300/60 bg-white/90 shadow-lg backdrop-blur-sm transition-all duration-200 hover:scale-105 active:scale-95 dark:border-neutral-700/60 dark:bg-neutral-800/90"
    @click.prevent="toggleLightDark"
    aria-label="Toggle theme"
  >
    <component :is="getIcon()" class="size-6 opacity-90 transition-transform duration-200" />
    <span class="sr-only">Toggle theme</span>
  </Button>
</template>