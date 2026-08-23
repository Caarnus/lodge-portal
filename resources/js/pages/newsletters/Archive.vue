<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import { formatLodgeDate } from "@/utils/date";
import { Head, Link } from "@inertiajs/vue3";
defineOptions({ layout: AppLayout });
const props = defineProps<{ lodge: any; issues: any[] }>();
const formatDate = (value: string | null) =>
    formatLodgeDate(value, props.lodge.date_display_format);
</script>
<template>
    <Head title="Newsletters" />
    <main class="mx-auto max-w-4xl p-6">
        <h1 class="text-3xl font-bold">{{ lodge.name }} newsletters</h1>
        <div class="mt-6 divide-y rounded border">
            <Link
                v-for="issue in issues"
                :key="issue.id"
                :href="`/lodges/${lodge.id}/newsletters/${issue.slug}`"
                class="block p-4 hover:bg-slate-50"
                ><strong>{{ issue.published.title }}</strong>
                <p class="text-sm text-slate-600">
                    {{ formatDate(issue.published.publication_date) }}
                </p></Link
            >
        </div>
    </main>
</template>
