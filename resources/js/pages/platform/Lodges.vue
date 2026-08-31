<script lang="ts" setup>
import ExpandableText from "@/components/ExpandableText.vue";
import PageHeader from "@/components/PageHeader.vue";
import AppLayout from "@/layouts/AppLayout.vue";
import {Head, Link} from "@inertiajs/vue3";
import {ExternalLink, Pencil, Plus} from "lucide-vue-next";
import Tooltip from "primevue/tooltip";

const vTooltip = Tooltip;

defineOptions({layout: AppLayout});

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
    <Head title="Lodges"/>

    <main class="mx-auto w-full max-w-6xl p-4 sm:p-6 lg:p-8">
        <PageHeader title="Lodges">
            <template #actions
            >
                <Link
                    v-tooltip.left="{ value: 'Create lodge', showDelay: 2000 }"
                    aria-label="Create lodge"
                    class="primary-button size-10 shrink-0 p-0"
                    href="/platform/lodges/create"
                >
                    <Plus aria-hidden="true" class="size-5"/>
                </Link
                >
            </template>
        </PageHeader>

        <div
            class="mt-6 overflow-hidden rounded-lg border border-border/80 bg-card"
        >
            <div
                class="hidden grid-cols-[minmax(0,2fr)_minmax(0,1.5fr)_minmax(7rem,auto)_5.25rem] gap-4 bg-muted px-4 py-3 text-sm font-semibold text-muted-foreground sm:grid"
            >
                <span>Lodge</span>
                <span>Location</span>
                <span>Status</span>
                <span class="sr-only">Actions</span>
            </div>

            <div
                v-for="lodge in lodges"
                :key="lodge.id"
                class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-3 border-t border-border/80 p-4 first:border-t-0 sm:grid-cols-[minmax(0,2fr)_minmax(0,1.5fr)_minmax(7rem,auto)_5.25rem] sm:gap-4 sm:first:border-t"
            >
                <div class="min-w-0">
                    <span
                        class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted-foreground sm:hidden"
                    >Lodge</span
                    >
                    <ExpandableText
                        :text="`${lodge.name} No. ${lodge.number}`"
                        class="font-medium"
                        label="lodge name"
                    />
                </div>
                <div class="min-w-0">
                    <span
                        class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted-foreground sm:hidden"
                    >Location</span
                    >
                    <ExpandableText :text="location(lodge)" label="location"/>
                </div>
                <div class="min-w-0">
                    <span
                        class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted-foreground sm:hidden"
                    >Status</span
                    >
                    <ExpandableText
                        :text="lodge.status"
                        class="capitalize"
                        label="status"
                    />
                </div>
                <div class="row-start-1 flex justify-end gap-1 sm:col-start-4">
                    <a
                        v-if="lodge.public_site_url"
                        v-tooltip.left="{
                            value: `Visit ${lodge.name} public site`,
                            showDelay: 2000,
                        }"
                        :aria-label="`Visit ${lodge.name} public site`"
                        :href="lodge.public_site_url"
                        class="icon-button text-muted-foreground"
                        rel="noopener"
                        target="_blank"
                    >
                        <ExternalLink aria-hidden="true" class="size-4"/>
                    </a>
                    <Link
                        v-tooltip.left="{
                            value: `Edit ${lodge.name}`,
                            showDelay: 2000,
                        }"
                        :aria-label="`Edit ${lodge.name}`"
                        :href="`/platform/lodges/${lodge.id}/edit`"
                        class="icon-button text-muted-foreground"
                    >
                        <Pencil aria-hidden="true" class="size-4"/>
                    </Link>
                </div>
            </div>

            <p
                v-if="lodges.length === 0"
                class="p-8 text-center text-sm text-muted-foreground"
            >
                No lodges found.
            </p>
        </div>
    </main>
</template>
