<script setup lang="ts">
import PublicAccountControls from "@/components/website/PublicAccountControls.vue";
import PublicNavigationItem from "@/components/website/PublicNavigationItem.vue";
import { formatLodgeDate } from "@/utils/date";
import { Head } from "@inertiajs/vue3";

const props = defineProps<{
    lodge: any;
    issue: any;
    version: any;
    navigation: any[];
    newsletterIndexUrl: string;
}>();

const formatDate = (value: string | null) =>
    formatLodgeDate(value, props.lodge.date_display_format);
</script>

<template>
    <Head :title="`${version.title} — ${lodge.name}`" />
    <div class="flex min-h-dvh flex-col bg-background text-foreground">
        <header
            class="border-b"
            :style="{ borderColor: lodge.secondary_color }"
        >
            <div
                class="mx-auto flex max-w-7xl flex-wrap items-center gap-4 px-5 py-4"
            >
                <a
                    :href="`/l/${lodge.slug}`"
                    class="flex min-w-0 items-center gap-3 font-bold"
                >
                    <img
                        v-if="lodge.seal_path || lodge.logo_path"
                        :src="`/storage/${lodge.seal_path || lodge.logo_path}`"
                        alt=""
                        class="size-12 object-contain"
                    />
                    <span class="truncate text-xl">{{ lodge.name }}</span>
                </a>
                <div
                    class="ml-auto flex flex-wrap items-center justify-end gap-3"
                >
                    <nav aria-label="Main navigation">
                        <ul class="flex flex-wrap justify-end gap-1">
                            <PublicNavigationItem
                                v-for="item in navigation"
                                :key="item.slug"
                                :item="item"
                                :lodge-slug="lodge.slug"
                            />
                        </ul>
                    </nav>
                    <PublicAccountControls />
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-4xl flex-1 px-5 py-10">
            <a :href="newsletterIndexUrl" class="text-sm font-medium underline"
                >← All newsletters</a
            >
            <h1 class="mt-4 text-3xl font-bold">{{ version.title }}</h1>
            <p
                v-if="version.publication_date"
                class="mt-1 text-muted-foreground"
            >
                {{ formatDate(version.publication_date) }}
            </p>
            <img
                v-if="version.cover_media_asset_id"
                :src="`/lodges/${lodge.id}/newsletters/${issue.slug}/cover`"
                class="mt-6 max-h-96 rounded-lg"
                alt="Newsletter cover"
            />
            <a
                v-if="version.newsletter_document_id"
                :href="`/lodges/${lodge.id}/newsletters/${issue.slug}/document`"
                class="mt-6 inline-block font-medium underline"
                >Download PDF</a
            >
            <article
                v-if="version.body_html"
                class="public-rich-text mt-8"
                v-html="version.body_html"
            />
        </main>

        <footer
            class="mt-12 border-t bg-slate-950 px-5 py-10 text-center text-sm text-white"
        >
            <p class="font-semibold">{{ lodge.name }}</p>
            <p class="mt-1">{{ lodge.city }}, {{ lodge.state }}</p>
        </footer>
    </div>
</template>
