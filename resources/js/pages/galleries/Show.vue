<script setup lang="ts">
import PublicAccountControls from "@/components/website/PublicAccountControls.vue";
import { Dialog, DialogContent } from "@/components/ui/dialog";
import PublicNavigationItem from "@/components/website/PublicNavigationItem.vue";
import { Head } from "@inertiajs/vue3";
import { ref } from "vue";
const props = defineProps<{
    lodge: any;
    album: any;
    version: any;
    navigation: any[];
    galleryIndexUrl: string;
}>();
const photo = (id: number) =>
    `/l/${props.lodge.slug}/galleries/${props.album.slug}/photos/${id}`;
const selectedPhoto = ref<any | null>(null);
</script>
<template>
    <Head :title="version.title" />
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
        <main class="mx-auto w-full max-w-6xl flex-1 px-5 py-10">
            <a :href="galleryIndexUrl" class="text-sm font-medium underline"
                >← All galleries</a
            >
            <h1 class="mt-4 text-3xl font-bold">{{ version.title }}</h1>
            <p v-if="version.description" class="mt-2 text-muted-foreground">
                {{ version.description }}
            </p>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <figure v-for="item in version.photos" :key="item.id">
                    <button
                        type="button"
                        class="block w-full overflow-hidden rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        @click="selectedPhoto = item"
                    >
                        <img
                            :src="photo(item.id)"
                            :alt="item.media_asset.alt_text"
                            class="aspect-square w-full object-cover transition duration-200 hover:scale-[1.02]"
                        />
                    </button>
                    <figcaption v-if="item.caption" class="mt-2 text-sm">
                        {{ item.caption }}
                    </figcaption>
                </figure>
            </div>
        </main>
        <footer
            class="border-t border-border/80 bg-foreground px-5 py-10 text-center text-sm text-background"
        >
            <p class="font-semibold">{{ lodge.name }}</p>
            <p class="mt-1">{{ lodge.city }}, {{ lodge.state }}</p>
        </footer>
    </div>
    <Dialog
        :open="selectedPhoto !== null"
        @update:open="!$event && (selectedPhoto = null)"
    >
        <DialogContent class="w-[calc(100vw-2rem)] max-w-6xl p-3 sm:p-4">
            <img
                v-if="selectedPhoto"
                :src="photo(selectedPhoto.id)"
                :alt="selectedPhoto.media_asset.alt_text"
                class="max-h-[calc(100vh-5rem)] w-full rounded-md object-contain"
            />
            <p v-if="selectedPhoto?.caption" class="px-1 text-sm">
                {{ selectedPhoto.caption }}
            </p>
        </DialogContent>
    </Dialog>
</template>
