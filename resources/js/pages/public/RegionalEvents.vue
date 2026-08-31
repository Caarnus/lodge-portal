<script lang="ts" setup>
import AppearanceTabs from "@/components/AppearanceTabs.vue";
import PageHeader from "@/components/PageHeader.vue";
import {Badge} from "@/components/ui/badge";
import {Card, CardContent} from "@/components/ui/card";
import type {SharedData} from "@/types";
import {Head, Link, router, useForm, usePage} from "@inertiajs/vue3";
import {computed, ref} from "vue";

const props = defineProps<{
    events: { data: Array<any>; links: Array<any>; last_page: number };
    filters: Record<string, string>;
    filterOptions: {
        groups: Array<any>;
        lodges: Array<any>;
        categories: Array<any>;
    };
    canViewProtectedEvents: boolean;
}>();
const page = usePage<SharedData>();
const view = ref<"list" | "calendar">("list");
const filters = useForm({
    group: props.filters.group ?? "",
    lodge: props.filters.lodge ?? "",
    category: props.filters.category ?? "",
    visibility: props.filters.visibility ?? "",
    qualification: props.filters.qualification ?? "",
    from: props.filters.from ?? "",
    to: props.filters.to ?? "",
});
const search = () =>
    router.get("/events", filters.data(), {
        preserveState: true,
        replace: true,
    });
const days = computed(() =>
    Object.entries(
        props.events.data.reduce((items: Record<string, any[]>, event: any) => {
            const day = event.starts_at.slice(0, 10);
            (items[day] ??= []).push(event);
            return items;
        }, {}),
    ).map(([date, events]) => ({date, events})),
);
</script>

<template>
    <Head title="WorkingTools events"/>
    <main class="min-h-dvh bg-background px-5 py-8 text-foreground sm:py-12">
        <div class="mx-auto max-w-6xl">
            <header class="flex items-center justify-between gap-4">
                <Link class="text-lg font-semibold" href="/lodges"
                >WorkingTools
                </Link
                >
                <div class="flex gap-3">
                    <AppearanceTabs compact/>
                    <Link
                        :href="
                            page.props.auth.user
                                ? route('dashboard')
                                : route('login')
                        "
                        class="rounded border bg-background px-3 py-2 text-sm"
                    >{{ page.props.auth.user ? "Portal" : "Log in" }}
                    </Link
                    >
                </div>
            </header>
            <section class="py-10">
                <PageHeader
                    :description="`Public events from active WorkingTools lodges.${!canViewProtectedEvents ? ' Sign in to see Masonic events you qualify for.' : ''}`"
                    title="Regional events"
                />
            </section>
            <form
                class="grid gap-3 rounded-xl border border-border/80 bg-card p-4 md:grid-cols-4"
                @submit.prevent="search"
            >
                <select v-model="filters.group" class="field-input">
                    <option value="">All groups</option>
                    <option
                        v-for="group in filterOptions.groups"
                        :key="group.id"
                        :value="group.slug"
                    >
                        {{ group.name }}
                    </option>
                </select
                ><select v-model="filters.lodge" class="field-input">
                <option value="">All lodges</option>
                <option
                    v-for="lodge in filterOptions.lodges"
                    :key="lodge.id"
                    :value="lodge.slug"
                >
                    {{ lodge.name }} No. {{ lodge.number }}
                </option>
            </select
            ><select v-model="filters.category" class="field-input">
                <option value="">All categories</option>
                <option
                    v-for="category in filterOptions.categories"
                    :key="category.key"
                    :value="category.key"
                >
                    {{ category.name }}
                </option>
            </select
            ><select v-model="filters.visibility" class="field-input">
                <option value="">All visible events</option>
                <option value="public">Public</option>
                <template v-if="canViewProtectedEvents"
                >
                    <option value="masons">Masons</option>
                    <option value="lodge">Lodge</option>
                </template
                >
            </select
            ><input
                v-model="filters.from"
                aria-label="From date"
                class="field-input"
                type="date"
            /><input
                v-model="filters.to"
                aria-label="To date"
                class="field-input"
                type="date"
            /><select
                v-if="canViewProtectedEvents"
                v-model="filters.qualification"
                class="field-input"
            >
                <option value="">All qualifications</option>
                <option value="ea">Entered Apprentice</option>
                <option value="fc">Fellow Craft</option>
                <option value="mm">Master Mason</option>
                <option value="pm">Past Master</option>
            </select
            >
                <button :disabled="filters.processing" class="primary-button">
                    Apply filters
                </button>
            </form>
            <div class="mt-6 flex gap-2">
                <button
                    :class="
                        view === 'list'
                            ? 'bg-primary text-primary-foreground'
                            : ''
                    "
                    class="secondary-button"
                    @click="view = 'list'"
                >
                    List
                </button
                >
                <button
                    :class="
                        view === 'calendar'
                            ? 'bg-primary text-primary-foreground'
                            : ''
                    "
                    class="secondary-button"
                    @click="view = 'calendar'"
                >
                    Calendar
                </button>
            </div>
            <section
                v-if="events.data.length && view === 'list'"
                class="mt-4 grid gap-4 md:grid-cols-2"
            >
                <Card v-for="event in events.data" :key="event.id"
                >
                    <CardContent class="p-5"
                    ><p class="text-sm text-muted-foreground">
                        {{ event.lodge.name }} No. {{ event.lodge.number }}
                    </p>
                        <Badge
                            v-if="event.visibility !== 'public'"
                            class="mt-2"
                            variant="secondary"
                        >Members only
                        </Badge
                        >
                        <h2 class="mt-1 text-xl font-semibold">
                            {{ event.title }}
                        </h2>
                        <p class="mt-2 text-sm">{{ event.starts_at }}</p>
                        <p
                            v-if="event.location_name"
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            {{ event.location_name }}
                        </p>
                        <a
                            :href="event.url"
                            class="mt-4 inline-block text-sm font-medium underline"
                        >View event</a
                        ></CardContent
                    >
                </Card
                >
            </section>
            <section v-else-if="events.data.length" class="mt-4 space-y-5">
                <Card v-for="day in days" :key="day.date"
                >
                    <CardContent class="p-5"
                    ><h2 class="font-semibold">{{ day.date }}</h2>
                        <ul class="mt-3 space-y-2">
                            <li v-for="event in day.events" :key="event.id">
                                <a :href="event.url" class="underline">{{
                                        event.title
                                    }}</a>
                                <span class="text-sm text-muted-foreground"
                                >— {{ event.lodge.name }}</span
                                >
                            </li>
                        </ul>
                    </CardContent
                    >
                </Card
                >
            </section>
            <p
                v-else
                class="mt-6 rounded-xl border border-dashed border-border/80 bg-card p-8 text-center text-muted-foreground"
            >
                No events match these filters.
            </p>
        </div>
    </main>
</template>
