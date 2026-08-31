<script lang="ts" setup>
import {Link} from "@inertiajs/vue3";
import type {Method} from "@inertiajs/core";

interface Props {
    href: string;
    tabindex?: number;
    method?: Method;
    as?: string;
}

defineProps<Props>();
</script>

<template>
    <Link
        :as="as"
        :href="href"
        :method="method"
        :tabindex="tabindex"
        class="text-primary underline decoration-primary/30 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current"
    >
        <slot/>
    </Link>
</template>
