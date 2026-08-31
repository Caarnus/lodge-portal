<script lang="ts" setup>
import AppLayout from "@/layouts/AppLayout.vue";
import {formatLodgeDate} from "@/utils/date";
import {Head, Link} from "@inertiajs/vue3";

defineOptions({layout: AppLayout});
const props = defineProps<{ lodge: any; issue: any; version: any }>();
const formatDate = (value: string | null) =>
    formatLodgeDate(value, props.lodge.date_display_format);
</script>
<template>
    <Head :title="version.title"/>
    <main class="mx-auto max-w-4xl p-6">
        <Link :href="`/lodges/${lodge.id}/newsletters`" class="underline"
        >← Archive
        </Link
        >
        <h1 class="mt-4 text-3xl font-bold">{{ version.title }}</h1>
        <p class="mt-1 text-muted-foreground">
            {{ formatDate(version.publication_date) }}
        </p>
        <img
            v-if="version.cover_media_asset_id"
            :src="`/lodges/${lodge.id}/newsletters/${issue.slug}/cover`"
            alt="Newsletter cover"
            class="mt-5 max-h-96 rounded"
        /><a
        v-if="version.newsletter_document_id"
        :href="`/lodges/${lodge.id}/newsletters/${issue.slug}/document`"
        class="mt-5 inline-block underline"
    >Download PDF</a
    >
        <article
            v-if="version.body_html"
            class="public-rich-text mt-6"
            v-html="version.body_html"
        />
    </main>
</template>
