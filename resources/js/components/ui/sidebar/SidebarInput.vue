<script lang="ts" setup>
import Input from "@/components/ui/input/Input.vue";
import {cn} from "@/lib/utils";
import type {HTMLAttributes} from "vue";

const props = defineProps<{
    class?: HTMLAttributes["class"];
}>();
</script>

<template>
    <Input
        :class="
            cn(
                'h-8 w-full bg-background shadow-none focus-visible:ring-2 focus-visible:ring-sidebar-ring',
                props.class,
            )
        "
        data-sidebar="input"
    >
        <slot/>
    </Input>
</template>
