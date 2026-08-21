<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';

defineOptions({ layout: AppLayout });
const props = defineProps<{ lodge: { id: number; name: string }; event: { id: number; title: string }; occurrences: { data: Array<any> } }>();
const transition = (occurrence: any, action: 'cancel' | 'restore') => router.post(`/lodges/${props.lodge.id}/events/${props.event.id}/occurrences/${occurrence.id}/${action}`);
</script>

<template><main class="mx-auto max-w-6xl space-y-6 p-6"><div><Link :href="`/lodges/${lodge.id}/events/${event.id}/edit`" class="text-sm text-primary underline">Back to event</Link><h1 class="mt-2 text-2xl font-semibold">Occurrences: {{ event.title }}</h1></div><div class="overflow-hidden rounded-lg border"><table class="w-full text-left text-sm"><thead class="bg-muted/50"><tr><th class="px-4 py-3">Effective start</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></thead><tbody><tr v-for="occurrence in occurrences.data" :key="occurrence.id" class="border-t"><td class="px-4 py-3">{{ new Date(occurrence.starts_at).toLocaleString() }}</td><td class="px-4 py-3 capitalize">{{ occurrence.status }}</td><td class="px-4 py-3 text-right"><Link :href="`/lodges/${lodge.id}/events/${event.id}/occurrences/${occurrence.id}/reservations`" class="mr-3 text-primary underline">Roster</Link><button v-if="occurrence.status === 'scheduled'" class="cursor-pointer text-destructive underline" @click="transition(occurrence, 'cancel')">Cancel</button><button v-else class="cursor-pointer text-primary underline" @click="transition(occurrence, 'restore')">Restore</button></td></tr></tbody></table></div></main></template>
