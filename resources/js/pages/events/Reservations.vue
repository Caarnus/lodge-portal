<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
defineOptions({ layout: AppLayout });
defineProps<{ event: { title: string }; reservations: { data: any[] } }>();
</script>
<template><main class="mx-auto max-w-6xl space-y-6 p-6"><h1 class="text-2xl font-semibold">Reservations: {{ event.title }}</h1><div class="overflow-hidden rounded-lg border"><table class="w-full text-left text-sm"><thead class="bg-muted/50"><tr><th class="px-4 py-3">Name</th><th class="px-4 py-3">Email</th><th class="px-4 py-3">Phone</th><th class="px-4 py-3">People</th><th class="px-4 py-3">Status</th></tr></thead><tbody><tr v-for="reservation in reservations.data" :key="reservation.id" class="border-t"><td class="px-4 py-3">{{ reservation.name }}</td><td class="px-4 py-3">{{ reservation.email }}</td><td class="px-4 py-3">{{ reservation.phone }}</td><td class="px-4 py-3">{{ reservation.party_size }}</td><td class="px-4 py-3 capitalize">{{ reservation.status }}</td></tr></tbody></table></div></main></template>
