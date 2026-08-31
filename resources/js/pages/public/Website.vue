<script setup lang="ts">
import PublicNavigationItem from "@/components/website/PublicNavigationItem.vue";
import PublicSection from "@/components/website/PublicSection.vue";
import PublicAccountControls from "@/components/website/PublicAccountControls.vue";
import { Head } from "@inertiajs/vue3";
import { computed } from "vue";

const props = defineProps<{
    lodge: any;
    page: any;
    navigation: any[];
    media: Record<string, any>;
    preview: boolean;
    officers: any[];
    pastMasters: any[];
    events: any[];
    galleries: any[];
    newsletters: any[];
    memberContent: { directory: boolean; newsletters: boolean };
}>();
const contrast = (hex: string) => {
    const value = hex.replace("#", "");
    const [r, g, b] = [0, 2, 4].map((index) =>
        parseInt(value.slice(index, index + 2), 16),
    );
    return (r * 299 + g * 587 + b * 114) / 1000 > 145 ? "#0f172a" : "#ffffff";
};
const primaryForeground = computed(() => contrast(props.lodge.primary_color));
const secondaryForeground = computed(() =>
    contrast(props.lodge.secondary_color),
);
</script>

<template>
    <Head :title="`${page.title} — ${lodge.name}`" />
    <div class="flex min-h-dvh flex-col bg-background text-foreground">
        <div
            v-if="preview"
            class="bg-amber-300 px-4 py-2 text-center text-sm font-semibold text-amber-950"
        >
            Draft preview — public visitors cannot see these changes.
        </div>
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
                    ><img
                        v-if="lodge.seal_path || lodge.logo_path"
                        :src="`/storage/${lodge.seal_path || lodge.logo_path}`"
                        alt=""
                        class="size-12 object-contain"
                    /><span class="truncate text-xl">{{ lodge.name }}</span></a
                >
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
        <main class="flex-1">
            <PublicSection
                v-for="section in page.sections"
                :key="section.id"
                :section="section"
                :lodge="lodge"
                :media="media"
                :officers="officers"
                :past-masters="pastMasters"
                :events="events"
                :galleries="galleries"
                :gallery-page-slug="page.is_home ? 'home' : page.slug"
                :newsletters="newsletters"
                :member-content="memberContent"
                :primary-foreground="primaryForeground"
                :secondary-foreground="secondaryForeground"
            />
        </main>
        <footer
            class="mt-12 border-t border-border/80 bg-foreground px-5 py-10 text-center text-sm text-background"
        >
            <p class="font-semibold">{{ lodge.name }}</p>
            <p class="mt-1">{{ lodge.city }}, {{ lodge.state }}</p>
        </footer>
    </div>
</template>
