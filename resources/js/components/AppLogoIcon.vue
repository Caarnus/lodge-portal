<script lang="ts" setup>
import type {HTMLAttributes} from "vue";

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes["class"];
}

defineProps<Props>();
</script>

<template>
    <img
        :class="className"
        :src="'/favicon.png'"
        alt=""
        aria-hidden="true"
        v-bind="$attrs"
    />
</template>
