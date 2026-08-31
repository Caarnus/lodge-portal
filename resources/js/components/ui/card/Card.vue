<script setup lang="ts">
import { cn } from "@/lib/utils";
import type { HTMLAttributes } from "vue";

const props = defineProps<{
    class?: HTMLAttributes["class"];
}>();
</script>

<template>
    <div
        :class="
            cn(
                'rounded-lg border border-border/80 bg-card text-card-foreground shadow-[0_1px_2px_rgb(15_23_42/0.04)] dark:shadow-black/10',
                props.class,
            )
        "
    >
        <slot />
    </div>
</template>
