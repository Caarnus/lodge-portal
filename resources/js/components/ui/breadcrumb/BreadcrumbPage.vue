<script lang="ts" setup>
import {cn} from "@/lib/utils";
import type {HTMLAttributes} from "vue";

const props = defineProps<{
    class?: HTMLAttributes["class"];
}>();
</script>

<template>
    <span
        :class="cn('font-normal text-foreground', props.class)"
        aria-current="page"
        aria-disabled="true"
        role="link"
    >
        <slot/>
    </span>
</template>
