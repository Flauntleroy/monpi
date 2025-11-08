<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/composables/useAppearance';
import { Moon, Sun } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref } from 'vue';

const { appearance, updateAppearance } = useAppearance();

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
    variant="ghost"
    size="icon"
    class="group h-9 w-9 cursor-pointer"
    @click.prevent="toggleLightDark"
    aria-label="Toggle theme"
  >
    <component :is="getIcon()" class="size-5 opacity-80 group-hover:opacity-100" />
    <span class="sr-only">Toggle theme</span>
  </Button>
</template>