<script setup lang="ts">
import { useAppearance } from "@/composables/useAppearance";
import { Monitor, Moon, Sun } from "lucide-vue-next";

interface Props {
    class?: string;
    compact?: boolean;
}

const { class: containerClass = "", compact = false } = defineProps<Props>();

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: "light", Icon: Sun, label: "Light" },
    { value: "dark", Icon: Moon, label: "Dark" },
    { value: "system", Icon: Monitor, label: "System" },
] as const;
</script>

<template>
    <div :class="['inline-flex gap-1 rounded-lg bg-muted p-1', containerClass]">
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            :aria-label="`${label} appearance`"
            :title="`${label} appearance`"
            @click="updateAppearance(value)"
            :class="[
                'flex items-center rounded-md py-1.5 transition-colors',
                compact ? 'justify-center px-2' : 'px-3.5',
                appearance === value
                    ? 'bg-card text-foreground shadow-sm'
                    : 'text-muted-foreground hover:bg-accent hover:text-foreground',
            ]"
        >
            <component
                :is="Icon"
                :class="compact ? 'size-4' : '-ml-1 h-4 w-4'"
            />
            <span v-if="!compact" class="ml-1.5 text-sm">{{ label }}</span>
        </button>
    </div>
</template>
