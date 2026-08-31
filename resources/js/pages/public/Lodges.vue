<script lang="ts" setup>
import AppearanceTabs from "@/components/AppearanceTabs.vue";
import PageHeader from "@/components/PageHeader.vue";
import {Badge} from "@/components/ui/badge";
import {Card, CardContent} from "@/components/ui/card";
import {Head, Link, router, useForm, usePage} from "@inertiajs/vue3";
import type {SharedData} from "@/types";

type Group = { id: number; name: string; slug: string };
type GroupType = { id: number; key: string; name: string };
type Lodge = {
    id: number;
    name: string;
    number: string;
    slug: string;
    city: string;
    state: string;
    jurisdiction: string;
    meeting_location: string | null;
    meeting_schedule: string | null;
    public_email: string;
    public_phone: string | null;
    logo_url: string | null;
    homepage_url: string | null;
    groups: Group[];
};
const props = defineProps<{
    lodges: {
        data: Lodge[];
        current_page: number;
        last_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    groups: Group[];
    groupTypes: GroupType[];
    filters: {
        group?: string;
        group_type?: string;
        query?: string;
        city?: string;
    };
}>();
const page = usePage<SharedData>();
const filters = useForm({
    group: props.filters.group ?? "",
    group_type: props.filters.group_type ?? "",
    query: props.filters.query ?? "",
    city: props.filters.city ?? "",
});
const search = () =>
    router.get("/lodges", filters.data(), {
        preserveState: true,
        replace: true,
    });
</script>

<template>
    <Head title="WorkingTools lodges"/>
    <main class="min-h-dvh bg-background px-5 py-8 text-foreground sm:py-12">
        <div class="mx-auto max-w-6xl">
            <header class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Link class="text-lg font-semibold" href="/"
                    >WorkingTools lodges
                    </Link
                    >
                    <Link class="text-sm underline" href="/events"
                    >Events
                    </Link
                    >
                </div>
                <div class="flex items-center gap-3">
                    <AppearanceTabs compact/>
                    <Link
                        :href="
                            page.props.auth.user
                                ? route('dashboard')
                                : route('login')
                        "
                        class="rounded-md border bg-background px-3 py-2 text-sm font-medium"
                    >{{ page.props.auth.user ? "Portal" : "Log in" }}
                    </Link
                    >
                </div>
            </header>
            <section class="py-10 sm:py-14">
                <PageHeader
                    description="Browse every active lodge. Meeting details and published websites appear when available."
                    title="Find a WorkingTools lodge"
                />
            </section>
            <form
                class="grid gap-3 rounded-xl border border-border/80 bg-card p-4 md:grid-cols-5"
                @submit.prevent="search"
            >
                <input
                    v-model="filters.query"
                    aria-label="Lodge name or number"
                    class="field-input"
                    placeholder="Lodge name or number"
                />
                <input
                    v-model="filters.city"
                    aria-label="City"
                    class="field-input"
                    placeholder="City"
                />
                <select
                    v-model="filters.group"
                    aria-label="Lodge group"
                    class="field-input"
                >
                    <option value="">All public groups</option>
                    <option
                        v-for="group in props.groups"
                        :key="group.id"
                        :value="group.slug"
                    >
                        {{ group.name }}
                    </option>
                </select>
                <select
                    v-model="filters.group_type"
                    aria-label="Lodge group type"
                    class="field-input"
                >
                    <option value="">All group types</option>
                    <option
                        v-for="type in props.groupTypes"
                        :key="type.id"
                        :value="type.key"
                    >
                        {{ type.name }}
                    </option>
                </select>
                <button :disabled="filters.processing" class="primary-button">
                    Search
                </button>
            </form>
            <section
                v-if="props.lodges.data.length"
                class="mt-6 grid gap-4 md:grid-cols-2"
            >
                <Card
                    v-for="lodge in props.lodges.data"
                    :key="lodge.id"
                    class="shadow-sm"
                >
                    <CardContent class="p-5">
                        <div class="flex gap-4">
                            <img
                                v-if="lodge.logo_url"
                                :src="lodge.logo_url"
                                alt=""
                                class="size-16 object-contain"
                            />
                            <div>
                                <h2 class="text-lg font-semibold">
                                    {{
                                        lodge.name
                                    }}
                                    <template v-if="lodge.number">
                                        No. {{ lodge.number }}
                                    </template
                                    >
                                </h2>
                                <p class="text-sm text-muted-foreground">
                                    {{ lodge.city }}, {{ lodge.state }} ·
                                    {{ lodge.jurisdiction }}
                                </p>
                            </div>
                        </div>
                        <dl class="mt-4 grid gap-2 text-sm">
                            <div v-if="lodge.meeting_location">
                                <dt class="font-medium">Where</dt>
                                <dd>{{ lodge.meeting_location }}</dd>
                            </div>
                            <div v-if="lodge.meeting_schedule">
                                <dt class="font-medium">When</dt>
                                <dd>{{ lodge.meeting_schedule }}</dd>
                            </div>
                            <div>
                                <dt class="font-medium">Contact</dt>
                                <dd>
                                    <a
                                        :href="`mailto:${lodge.public_email}`"
                                        class="underline"
                                    >{{ lodge.public_email }}</a
                                    >
                                    <template v-if="lodge.public_phone">
                                        · {{ lodge.public_phone }}
                                    </template
                                    >
                                </dd>
                            </div>
                        </dl>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <Badge
                                v-for="group in lodge.groups"
                                :key="group.id"
                                variant="secondary"
                            >
                                <Link :href="`/groups/${group.slug}`">{{
                                        group.name
                                    }}
                                </Link>
                            </Badge
                            >
                        </div>
                        <a
                            v-if="lodge.homepage_url"
                            :href="lodge.homepage_url"
                            class="mt-4 inline-block text-sm font-medium underline"
                        >Visit lodge website</a
                        >
                        <p v-else class="mt-4 text-sm text-muted-foreground">
                            Website not published.
                        </p>
                    </CardContent>
                </Card
                >
            </section>
            <p
                v-else
                class="mt-6 rounded-xl border border-dashed border-border/80 bg-card p-8 text-center text-muted-foreground"
            >
                No active lodges match these filters.
            </p>
            <nav
                v-if="props.lodges.last_page > 1"
                aria-label="Lodge directory pages"
                class="mt-6 flex flex-wrap gap-2"
            >
                <a
                    v-for="link in props.lodges.links"
                    :key="link.label"
                    :class="[
                        'rounded-md border border-border/80 bg-card px-3 py-2 text-sm',
                        link.active ? 'bg-primary text-primary-foreground' : '',
                        !link.url ? 'pointer-events-none opacity-50' : '',
                    ]"
                    :href="link.url ?? '#'"
                    v-html="link.label"
                />
            </nav>
        </div>
    </main>
</template>
