<script setup lang="ts">
import ExpandableText from "@/components/ExpandableText.vue";
import AppLayout from "@/layouts/AppLayout.vue";
import { Head, Link } from "@inertiajs/vue3";
import { ExternalLink, Pencil, Plus } from "lucide-vue-next";
import Tooltip from "primevue/tooltip";

const vTooltip = Tooltip;

defineOptions({ layout: AppLayout });

interface Lodge {
    id: number;
    name: string;
    number: string | number;
    city?: string | null;
    state?: string | null;
    status: string;
    public_site_url?: string | null;
}

defineProps<{ lodges: Lodge[] }>();

const location = (lodge: Lodge) =>
    [lodge.city, lodge.state].filter(Boolean).join(", ");
</script>

<template>
    <Head title="Lodges" />

    <main class="mx-auto w-full max-w-6xl p-4 sm:p-6 lg:p-8">
        <div class="flex items-center justify-between gap-4">
            <h1 class="min-w-0 text-2xl font-bold sm:text-3xl">Lodges</h1>
            <Link
                href="/platform/lodges/create"
                aria-label="Create lodge"
                class="inline-flex size-10 shrink-0 items-center justify-center rounded-md bg-slate-900 text-white transition hover:bg-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                v-tooltip.left="{ value: 'Create lodge', showDelay: 2000 }"
            >
                <Plus class="size-5" aria-hidden="true" />
            </Link>
        </div>

        <div class="mt-6 overflow-hidden rounded-lg border border-slate-200">
            <div
                class="hidden grid-cols-[minmax(0,2fr)_minmax(0,1.5fr)_minmax(7rem,auto)_5.25rem] gap-4 bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-700 sm:grid"
            >
                <span>Lodge</span>
                <span>Location</span>
                <span>Status</span>
                <span class="sr-only">Actions</span>
            </div>

            <div
                v-for="lodge in lodges"
                :key="lodge.id"
                class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-3 border-t border-slate-200 p-4 first:border-t-0 sm:grid-cols-[minmax(0,2fr)_minmax(0,1.5fr)_minmax(7rem,auto)_5.25rem] sm:gap-4 sm:first:border-t"
            >
                <div class="min-w-0">
                    <span
                        class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 sm:hidden"
                        >Lodge</span
                    >
                    <ExpandableText
                        :text="`${lodge.name} No. ${lodge.number}`"
                        label="lodge name"
                        class="font-medium"
                    />
                </div>
                <div class="min-w-0">
                    <span
                        class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 sm:hidden"
                        >Location</span
                    >
                    <ExpandableText :text="location(lodge)" label="location" />
                </div>
                <div class="min-w-0">
                    <span
                        class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 sm:hidden"
                        >Status</span
                    >
                    <ExpandableText
                        :text="lodge.status"
                        label="status"
                        class="capitalize"
                    />
                </div>
                <div class="row-start-1 flex justify-end gap-1 sm:col-start-4">
                    <a
                        v-if="lodge.public_site_url"
                        :href="lodge.public_site_url"
                        target="_blank"
                        rel="noopener"
                        :aria-label="`Visit ${lodge.name} public site`"
                        class="inline-flex size-10 items-center justify-center rounded-md text-slate-600 transition hover:bg-slate-100 hover:text-slate-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                        v-tooltip.left="{
                            value: `Visit ${lodge.name} public site`,
                            showDelay: 2000,
                        }"
                    >
                        <ExternalLink class="size-4" aria-hidden="true" />
                    </a>
                    <Link
                        :href="`/platform/lodges/${lodge.id}/edit`"
                        :aria-label="`Edit ${lodge.name}`"
                        class="inline-flex size-10 items-center justify-center rounded-md text-slate-600 transition hover:bg-slate-100 hover:text-slate-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
                        v-tooltip.left="{
                            value: `Edit ${lodge.name}`,
                            showDelay: 2000,
                        }"
                    >
                        <Pencil class="size-4" aria-hidden="true" />
                    </Link>
                </div>
            </div>

            <p
                v-if="lodges.length === 0"
                class="p-8 text-center text-sm text-slate-500"
            >
                No lodges found.
            </p>
        </div>
    </main>
</template>
