<script setup lang="ts">
import Progress from "./Progress.vue";
import { computed } from "vue";

const props = withDefaults(
    defineProps<{
        label: string;
        value: number;
        max?: number;
        valueLabel?: string;
        description?: string;
    }>(),
    { max: 100, valueLabel: undefined, description: undefined },
);

const percentage = computed(() =>
    props.max > 0
        ? Math.min(100, Math.max(0, (props.value / props.max) * 100))
        : 0,
);
const displayedValue = computed(
    () => props.valueLabel ?? `${Math.round(percentage.value)}%`,
);
const accessibleValue = () =>
    `${displayedValue.value}, ${Math.round(percentage.value)} percent`;
</script>

<template>
    <div class="space-y-2">
        <div class="flex items-baseline justify-between gap-4 text-sm">
            <span class="font-medium">{{ label }}</span>
            <span class="shrink-0 font-semibold tabular-nums">
                {{ displayedValue }}
            </span>
        </div>
        <Progress
            :model-value="value"
            :max="max"
            :get-value-label="accessibleValue"
            :aria-label="label"
        />
        <p v-if="description" class="text-xs text-muted-foreground">
            {{ description }}
        </p>
    </div>
</template>
