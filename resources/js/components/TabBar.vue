<script lang="ts" setup>
import {Link} from "@inertiajs/vue3";

defineProps<{
    tabs: Array<{ key: string; label: string; href: string }>;
    active: string;
    ariaLabel: string;
}>();
</script>

<template>
    <nav
        :aria-label="ariaLabel"
        class="-mb-px flex gap-1 overflow-x-auto border-b border-border/80"
    >
        <Link
            v-for="tab in tabs"
            :key="tab.key"
            :aria-current="active === tab.key ? 'page' : undefined"
            :class="
                active === tab.key
                    ? 'border-primary text-primary'
                    : 'border-transparent text-muted-foreground hover:border-border hover:text-foreground'
            "
            :href="tab.href"
            class="shrink-0 border-b-2 px-3 py-2 text-sm font-medium transition-colors"
        >
            {{ tab.label }}
        </Link>
    </nav>
</template>
