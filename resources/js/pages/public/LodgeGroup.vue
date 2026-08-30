<script setup lang="ts">
import AppearanceTabs from "@/components/AppearanceTabs.vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import type { SharedData } from "@/types";

defineProps<{ group: { name: string; slug: string; description: string | null; type: string }; lodges: Array<any>; events: Array<any> }>();
const page = usePage<SharedData>();
</script>

<template>
    <Head :title="group.name" />
    <main class="min-h-dvh bg-muted/30 px-5 py-8 text-foreground sm:py-12"><div class="mx-auto max-w-5xl">
        <header class="flex items-center justify-between gap-4"><Link href="/lodges" class="text-lg font-semibold">WorkingTools lodges</Link><div class="flex gap-3"><AppearanceTabs compact /><Link :href="page.props.auth.user ? route('dashboard') : route('login')" class="rounded border bg-background px-3 py-2 text-sm">{{ page.props.auth.user ? "Portal" : "Log in" }}</Link></div></header>
        <section class="py-10"><p class="text-sm font-medium text-muted-foreground">{{ group.type }}</p><h1 class="mt-2 text-3xl font-bold sm:text-4xl">{{ group.name }}</h1><p v-if="group.description" class="mt-4 max-w-3xl text-muted-foreground">{{ group.description }}</p><div class="mt-5 flex gap-3"><Link :href="`/lodges?group=${group.slug}`" class="rounded border bg-background px-3 py-2 text-sm font-medium">Browse these lodges</Link><a :href="`/events?group=${group.slug}`" class="rounded border bg-background px-3 py-2 text-sm font-medium">View events</a></div></section>
        <section><h2 class="text-xl font-semibold">Member lodges</h2><div v-if="lodges.length" class="mt-4 grid gap-4 sm:grid-cols-2"><article v-for="lodge in lodges" :key="lodge.id" class="rounded-xl border bg-background p-5"><h3 class="font-semibold">{{ lodge.name }}<template v-if="lodge.number"> No. {{ lodge.number }}</template></h3><p class="mt-1 text-sm text-muted-foreground">{{ lodge.city }}, {{ lodge.state }}</p><p v-if="lodge.meeting_schedule" class="mt-3 text-sm">{{ lodge.meeting_schedule }}</p><a v-if="lodge.homepage_url" :href="lodge.homepage_url" class="mt-3 inline-block text-sm underline">Lodge website</a></article></div><p v-else class="mt-4 rounded border bg-background p-5 text-muted-foreground">No active lodges currently belong to this group.</p></section>
        <section class="mt-10"><h2 class="text-xl font-semibold">Upcoming public events</h2><div v-if="events.length" class="mt-4 grid gap-3"><a v-for="event in events" :key="event.id" :href="event.url" class="rounded-xl border bg-background p-4"><p class="font-semibold">{{ event.title }}</p><p class="mt-1 text-sm text-muted-foreground">{{ event.lodge.name }} No. {{ event.lodge.number }} · {{ event.starts_at }}</p><p v-if="event.location_name" class="mt-1 text-sm">{{ event.location_name }}</p></a></div><p v-else class="mt-4 rounded border bg-background p-5 text-muted-foreground">No upcoming public events.</p></section>
    </div></main>
</template>
