<script setup lang="ts">
import PublicAccountControls from "@/components/website/PublicAccountControls.vue";
import PublicNavigationItem from "@/components/website/PublicNavigationItem.vue";
import { Head, Link, router } from "@inertiajs/vue3";

const props = defineProps<{
    lodge: any;
    navigation: any[];
    occurrences: { data: Array<any> };
    range: string;
    rangeOptions: Array<{ key: string; label: string }>;
}>();
const date = (value: string, timeZone: string) =>
    new Intl.DateTimeFormat(undefined, {
        dateStyle: "full",
        timeStyle: "short",
        timeZone,
    }).format(new Date(value));
const changeRange = (event: Event) =>
    router.get(
        `/l/${props.lodge.slug}/events`,
        { range: (event.target as HTMLSelectElement).value },
        { preserveScroll: true },
    );
</script>

<template>
    <Head :title="`Events — ${lodge.name}`" />
    <div class="flex min-h-dvh flex-col bg-background text-foreground">
        <header
            class="border-b"
            :style="{ borderColor: lodge.secondary_color }"
        >
            <div
                class="mx-auto flex max-w-7xl flex-wrap items-center gap-4 px-5 py-4"
            >
                <a :href="`/l/${lodge.slug}`" class="font-bold text-xl">{{
                    lodge.name
                }}</a>
                <div class="ml-auto flex flex-wrap items-center gap-3">
                    <nav>
                        <ul class="flex flex-wrap gap-1">
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
        <main class="mx-auto w-full max-w-5xl flex-1 px-5 py-12">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-4xl font-bold">Upcoming events</h1>
                    <p class="mt-3 text-muted-foreground">
                        Gatherings, meetings, and opportunities from
                        {{ lodge.name }}.
                    </p>
                </div>
                <label class="text-sm font-medium"
                    >Show events<select
                        :value="range"
                        class="mt-1 block cursor-pointer rounded-md border bg-background px-3 py-2 text-base"
                        @change="changeRange"
                    >
                        <option
                            v-for="option in rangeOptions"
                            :key="option.key"
                            :value="option.key"
                        >
                            {{ option.label }}
                        </option>
                    </select></label
                >
            </div>
            <div
                v-if="occurrences.data.length"
                class="mt-8 grid gap-5 md:grid-cols-2"
            >
                <article
                    v-for="occurrence in occurrences.data"
                    :key="occurrence.id"
                    class="overflow-hidden rounded-xl border"
                >
                    <img
                        v-if="occurrence.cover_image"
                        :src="occurrence.cover_image"
                        alt=""
                        class="aspect-[16/8] w-full object-cover"
                    />
                    <div class="p-5">
                        <p
                            v-if="occurrence.category"
                            class="text-sm font-semibold uppercase tracking-wide text-muted-foreground"
                        >
                            {{ occurrence.category }}
                        </p>
                        <span
                            v-if="occurrence.visibility !== 'public'"
                            class="mt-2 inline-block rounded-full bg-muted px-2 py-1 text-xs font-medium"
                            >Members only</span
                        >
                        <h2 class="mt-1 text-2xl font-bold">
                            {{ occurrence.title }}
                        </h2>
                        <p class="mt-3 font-medium">
                            {{
                                date(occurrence.starts_at, occurrence.time_zone)
                            }}
                        </p>
                        <p
                            v-if="occurrence.location_name"
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            {{ occurrence.location_name }}
                        </p>
                        <Link
                            :href="`/l/${lodge.slug}/events/${occurrence.id}`"
                            class="mt-5 inline-block text-sm font-medium text-primary underline"
                            >View event</Link
                        >
                    </div>
                </article>
            </div>
            <p
                v-else
                class="mt-8 rounded-lg border border-dashed p-8 text-center text-muted-foreground"
            >
                There are no upcoming events available to you in this period.
            </p>
        </main>
        <footer
            class="border-t bg-slate-950 px-5 py-10 text-center text-sm text-white"
        >
            <p class="font-semibold">{{ lodge.name }}</p>
            <p class="mt-1">{{ lodge.city }}, {{ lodge.state }}</p>
        </footer>
    </div>
</template>
