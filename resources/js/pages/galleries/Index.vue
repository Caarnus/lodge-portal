<script lang="ts" setup>
import PublicAccountControls from "@/components/website/PublicAccountControls.vue";
import PublicNavigationItem from "@/components/website/PublicNavigationItem.vue";
import {Head} from "@inertiajs/vue3";

defineProps<{ lodge: any; albums: any[]; navigation: any[] }>();
</script>
<template>
    <Head :title="`Galleries — ${lodge.name}`"/>
    <div class="flex min-h-dvh flex-col bg-background text-foreground">
        <header
            :style="{ borderColor: lodge.secondary_color }"
            class="border-b"
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
                    <PublicAccountControls/>
                </div>
            </div>
        </header>
        <main class="mx-auto w-full max-w-6xl flex-1 px-5 py-10">
            <h1 class="text-3xl font-bold">{{ lodge.name }} galleries</h1>
            <div
                v-if="albums.length"
                class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <a
                    v-for="album in albums"
                    :key="album.id"
                    :href="`/l/${lodge.slug}/galleries/${album.slug}`"
                    class="rounded-xl border bg-card p-5 transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <h2 class="font-semibold">{{ album.published.title }}</h2>
                    <p
                        v-if="album.published.description"
                        class="mt-2 text-sm text-muted-foreground"
                    >
                        {{ album.published.description }}
                    </p>
                </a>
            </div>
            <p v-else class="mt-6 text-muted-foreground">
                No galleries available.
            </p>
        </main>
        <footer
            class="border-t border-border/80 bg-foreground px-5 py-10 text-center text-sm text-background"
        >
            <p class="font-semibold">{{ lodge.name }}</p>
            <p class="mt-1">{{ lodge.city }}, {{ lodge.state }}</p>
        </footer>
    </div>
</template>
