<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import type { Method } from "@inertiajs/core";

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
        :href="href"
        :tabindex="tabindex"
        :method="method"
        :as="as"
        class="text-primary underline decoration-primary/30 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current"
    >
        <slot />
    </Link>
</template>
