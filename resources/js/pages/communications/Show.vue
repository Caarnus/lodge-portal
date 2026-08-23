<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import { Head, Link } from "@inertiajs/vue3";
defineOptions({ layout: AppLayout });
defineProps<{ lodge: any; communication: any }>();
</script>
<template>
    <Head :title="communication.subject" />
    <main class="mx-auto max-w-4xl p-6">
        <Link :href="`/lodges/${lodge.id}/communications`" class="underline"
            >← Communications</Link
        >
        <h1 class="mt-4 text-3xl font-bold">{{ communication.subject }}</h1>
        <article
            class="public-rich-text mt-6"
            v-html="communication.body_html"
        />
    </main>
</template>
