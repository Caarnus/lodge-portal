<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="flex aspect-square size-8 shrink-0 items-center justify-center overflow-hidden rounded-md bg-white dark:bg-slate-100">
        <AppLogoIcon class="size-8 object-cover" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm group-data-[collapsible=icon]:hidden">
        <span class="mb-0.5 truncate font-semibold leading-none">WorkingTools</span>
    </div>
</template>
