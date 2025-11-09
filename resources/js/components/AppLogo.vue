<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const brandName = computed(() => {
  const fromProps = (page.props as any)?.name;
  const fromEnv = (import.meta as any)?.env?.VITE_APP_NAME;
  return fromProps || fromEnv || 'BPJS Monitoring';
});
</script>

<template>
    <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
        <AppLogoIcon class="size-5 fill-current text-white dark:text-black" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">MonPi</span>
    </div>
</template>
