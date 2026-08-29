<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { computed, reactive } from "vue";

const props = defineProps<{ requestingLodge: { id: number; name: string; number: string }; filters: Record<string, any>; results: { data: any[]; current_page: number; last_page: number; total: number }; categories: any[]; lodges: any[] }>();
const form = reactive({ audience: props.filters.audience ?? "own_lodge", category: props.filters.category ?? "", part: props.filters.part ?? "", lodge: props.filters.lodge ?? "", day_of_week: props.filters.day_of_week ?? "", daypart: props.filters.daypart ?? "", query: props.filters.query ?? "" });
const parts = computed(() => props.categories.flatMap((category) => category.parts.map((part: any) => ({ ...part, category_id: category.id, category_name: category.name }))));
const search = (page = 1) => router.get(`/lodges/${props.requestingLodge.id}/ritual-assistance`, { ...form, page }, { preserveState: true, preserveScroll: true });
const reset = () => { Object.assign(form, { audience: "own_lodge", category: "", part: "", lodge: "", day_of_week: "", daypart: "", query: "" }); search(); };
const dayName = (day: number) => ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"][day - 1];
</script>

<template>
    <Head title="Ritual Assistance" />
    <AppLayout :breadcrumbs="[{ title: requestingLodge.name, href: `/lodges/${requestingLodge.id}/ritual-assistance` }, { title: 'Ritual Assistance', href: `/lodges/${requestingLodge.id}/ritual-assistance` }]">
        <main class="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-6">
            <header><h1 class="text-2xl font-bold">Ritual Assistance</h1><p class="mt-1 text-sm text-muted-foreground">Proficiency and availability are self-reported. A listed member has not accepted an assignment; contact him separately.</p></header>
            <form class="grid gap-3 rounded-lg border p-4 md:grid-cols-4" @submit.prevent="search()">
                <label class="text-sm">Audience<select v-model="form.audience" class="mt-1 w-full rounded border bg-background p-2"><option value="own_lodge">Own lodge</option><option value="participating_lodges">Participating lodges</option></select></label>
                <label class="text-sm">Category<select v-model="form.category" class="mt-1 w-full rounded border bg-background p-2"><option value="">All active categories</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select></label>
                <label class="text-sm">Part<select v-model="form.part" class="mt-1 w-full rounded border bg-background p-2"><option value="">All active parts</option><option v-for="part in parts" :key="part.id" :value="part.id">{{ part.category_name }} — {{ part.name }}</option></select></label>
                <label class="text-sm">Lodge affiliation<select v-model="form.lodge" class="mt-1 w-full rounded border bg-background p-2"><option value="">Any active lodge</option><option v-for="item in lodges" :key="item.id" :value="item.id">{{ item.name }} · {{ item.number }}</option></select></label>
                <label class="text-sm">Weekday<select v-model="form.day_of_week" class="mt-1 w-full rounded border bg-background p-2"><option value="">Any weekday</option><option v-for="(day, index) in ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday']" :key="day" :value="index + 1">{{ day }}</option></select></label>
                <label class="text-sm">Daypart<select v-model="form.daypart" class="mt-1 w-full rounded border bg-background p-2"><option value="">Any daypart</option><option value="morning">Morning</option><option value="afternoon">Afternoon</option><option value="evening">Evening</option></select></label>
                <label class="text-sm md:col-span-2">Name<input v-model="form.query" class="mt-1 w-full rounded border bg-background p-2" maxlength="120" placeholder="Display name" /></label>
                <div class="flex gap-2 md:col-span-4"><button class="rounded bg-primary px-4 py-2 text-primary-foreground">Search</button><button type="button" class="rounded border px-4 py-2" @click="reset">Clear</button></div>
            </form>
            <p v-if="!results.total" class="rounded-lg border p-5 text-sm text-muted-foreground">No matching members. Try broader filters; hidden and unavailable profiles are not counted.</p>
            <section v-for="person in results.data" :key="person.id" class="rounded-lg border p-5"><div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="font-semibold"><Link class="underline" :href="`/lodges/${requestingLodge.id}/ritual-assistance/${person.id}?audience=${form.audience}`">{{ person.display_name }}</Link></h2><p class="mt-1 text-sm text-muted-foreground">{{ person.affiliations.map((item: any) => `${item.name} · ${item.number}`).join(' • ') }}</p></div><div class="text-right text-sm"><a v-if="person.email" :href="`mailto:${person.email}`" class="block underline">{{ person.email }}</a><a v-if="person.phone" :href="`tel:${person.phone}`" class="block underline">{{ person.phone }}</a></div></div><div class="mt-4"><strong class="text-sm">Matching ritual parts</strong><ul class="mt-1 list-disc pl-5 text-sm"><li v-for="part in person.parts" :key="part.id">{{ part.category }} — {{ part.name }} <span class="text-muted-foreground">· Self-reported · Updated {{ new Date(part.updated_at).toLocaleDateString() }}</span></li></ul></div><p v-if="person.availability.length || person.public_availability_note" class="mt-3 text-sm"><strong>Availability:</strong> {{ person.availability.map((item: any) => `${dayName(item.day_of_week)} ${item.daypart}`).join(', ') }}<span v-if="person.public_availability_note"> — {{ person.public_availability_note }}</span></p></section>
            <nav v-if="results.last_page > 1" class="flex items-center justify-between"><button class="rounded border px-3 py-2" :disabled="results.current_page === 1" @click="search(results.current_page - 1)">Previous</button><span class="text-sm">Page {{ results.current_page }} of {{ results.last_page }}</span><button class="rounded border px-3 py-2" :disabled="results.current_page === results.last_page" @click="search(results.current_page + 1)">Next</button></nav>
        </main>
    </AppLayout>
</template>
