<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import { formatLodgeDate } from "@/utils/date";
import { Head, Link } from "@inertiajs/vue3";
defineOptions({ layout: AppLayout });
const props = defineProps<{ lodge: any; communications: any[] }>();
const formatDate = (value: string | null) =>
    formatLodgeDate(value, props.lodge.date_display_format);
</script>
<template>
    <Head title="Communications" />
    <main class="mx-auto max-w-4xl space-y-6 p-4 md:p-6">
        <PageHeader
            :title="`${lodge.name} communications`"
            description="Browse previously sent lodge messages."
        />
        <div class="divide-y rounded-lg border border-border bg-card">
            <Link
                v-for="item in communications"
                :key="item.id"
                :href="`/lodges/${lodge.id}/communications/${item.id}`"
                class="block p-4 transition-colors hover:bg-muted"
                ><strong>{{ item.subject }}</strong>
                <p class="text-sm">{{ formatDate(item.sent_at) }}</p></Link
            >
        </div>
    </main>
</template>
