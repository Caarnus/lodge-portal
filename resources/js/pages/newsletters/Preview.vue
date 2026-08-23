<script setup lang="ts">
const props = defineProps<{ lodge: any; issue: any; version: any }>();
</script>
<template>
    <main class="mx-auto max-w-4xl p-6">
        <h1 class="text-3xl font-bold">{{ version.title }}</h1>
        <p>{{ version.publication_date }}</p>
        <img
            v-if="version.cover_media_asset?.url"
            :src="version.cover_media_asset.url"
            class="mt-5 max-h-96"
            alt="Newsletter cover"
        />
        <article class="public-rich-text mt-6" v-html="version.body_html" />
    </main>
</template>
