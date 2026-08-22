<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

defineProps<{ volunteerCommitments: Array<{ id: number; position: string; event: string; lodge: string; starts_at: string; time_zone: string; location: string | null; event_url: string }> }>();
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-4xl rounded-xl border p-6">
            <h1 class="text-xl font-semibold">Upcoming volunteer commitments</h1>
            <p v-if="!volunteerCommitments.length" class="mt-3 text-sm text-muted-foreground">No upcoming volunteer commitments.</p>
            <ul v-else class="mt-4 divide-y"><li v-for="commitment in volunteerCommitments" :key="commitment.id" class="py-4"><Link :href="commitment.event_url" class="font-medium text-primary underline">{{ commitment.position }} — {{ commitment.event }}</Link><p class="mt-1 text-sm text-muted-foreground">{{ commitment.lodge }} · {{ new Date(commitment.starts_at).toLocaleString(undefined, { timeZone: commitment.time_zone }) }}<span v-if="commitment.location"> · {{ commitment.location }}</span></p></li></ul>
        </div>
    </AppLayout>
</template>
