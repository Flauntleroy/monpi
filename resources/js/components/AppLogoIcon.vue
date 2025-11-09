<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { Activity } from 'lucide-vue-next';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <Activity :class="className" v-bind="$attrs" />
</template>
