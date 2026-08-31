<script lang="ts" setup>
import {cn} from "@/lib/utils";
import {ProgressIndicator, ProgressRoot} from "radix-vue";
import {computed, type HTMLAttributes} from "vue";

const props = withDefaults(
    defineProps<{
        modelValue?: number | null;
        max?: number;
        getValueLabel?: (value: number, max: number) => string;
        class?: HTMLAttributes["class"];
    }>(),
    {
        modelValue: 0,
        max: 100,
        getValueLabel: undefined,
    },
);

const percentage = computed(() => {
    if (props.modelValue === null || props.max <= 0) return 0;

    return Math.min(100, Math.max(0, (props.modelValue / props.max) * 100));
});
</script>

<template>
    <ProgressRoot
        :class="
            cn(
                'relative h-2.5 w-full overflow-hidden rounded-full bg-muted',
                props.class,
            )
        "
        :get-value-label="getValueLabel"
        :max="max"
        :model-value="modelValue"
    >
        <ProgressIndicator
            :style="{ transform: `translateX(-${100 - percentage}%)` }"
            class="h-full w-full rounded-full bg-primary transition-transform duration-300 ease-out"
        />
    </ProgressRoot>
</template>
