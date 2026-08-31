<script lang="ts" setup>
import {cn} from "@/lib/utils";
import {MoreHorizontal} from "lucide-vue-next";
import type {HTMLAttributes} from "vue";

const props = defineProps<{
    class?: HTMLAttributes["class"];
}>();
</script>

<template>
    <span
        :class="cn('flex h-9 w-9 items-center justify-center', props.class)"
        aria-hidden="true"
        role="presentation"
    >
        <slot>
            <MoreHorizontal class="h-4 w-4"/>
        </slot>
        <span class="sr-only">More</span>
    </span>
</template>
