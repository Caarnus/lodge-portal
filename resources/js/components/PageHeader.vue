<script lang="ts" setup>
defineProps<{
    title: string;
    description?: string;
    eyebrow?: string;
}>();
</script>

<template>
    <header
        class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
    >
        <div class="min-w-0 space-y-1.5">
            <p v-if="eyebrow" class="text-sm font-medium text-primary">
                {{ eyebrow }}
            </p>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                {{ title }}
            </h1>
            <p
                v-if="description"
                class="max-w-3xl text-sm leading-6 text-muted-foreground"
            >
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.actions" class="shrink-0">
            <slot name="actions"/>
        </div>
    </header>
</template>
