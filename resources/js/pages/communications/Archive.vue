<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import { formatLodgeDate } from "@/utils/date";
import { Head, Link } from "@inertiajs/vue3";
defineOptions({ layout: AppLayout });
const props = defineProps<{ lodge: any; communications: any[] }>();
const formatDate = (value: string | null) =>
    formatLodgeDate(value, props.lodge.date_display_format);
</script>
<template>
    <Head title="Communications" />
    <main class="mx-auto max-w-4xl p-6">
        <h1 class="text-3xl font-bold">{{ lodge.name }} communications</h1>
        <div class="mt-5 divide-y rounded border">
            <Link
                v-for="item in communications"
                :key="item.id"
                :href="`/lodges/${lodge.id}/communications/${item.id}`"
                class="block p-4 hover:bg-slate-50"
                ><strong>{{ item.subject }}</strong>
                <p class="text-sm">{{ formatDate(item.sent_at) }}</p></Link
            >
        </div>
    </main>
</template>
