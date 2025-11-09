<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import AppShell from '@/components/AppShell.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    fluid?: boolean;
    hideHeader?: boolean;
    hideBreadcrumbs?: boolean;
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    fluid: false,
    hideHeader: false,
    hideBreadcrumbs: false,
});
</script>

<template>
    <AppShell variant="header" class="flex-col">
        <AppHeader v-if="!hideHeader" :breadcrumbs="breadcrumbs" />
        <AppContent :class="fluid ? '!max-w-none !mx-0 px-4' : ''">
            <slot />
        </AppContent>
        <div v-if="!hideBreadcrumbs && breadcrumbs && breadcrumbs.length > 1" class="hidden"></div>
    </AppShell>
</template>
