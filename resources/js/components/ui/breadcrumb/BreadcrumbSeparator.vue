<script lang="ts" setup>
import {cn} from "@/lib/utils";
import {ChevronRight} from "lucide-vue-next";
import type {HTMLAttributes} from "vue";

const props = defineProps<{
    class?: HTMLAttributes["class"];
}>();
</script>

<template>
    <li
        :class="cn('[&>svg]:h-3.5 [&>svg]:w-3.5', props.class)"
        aria-hidden="true"
        role="presentation"
    >
        <slot>
            <ChevronRight/>
        </slot>
    </li>
</template>
