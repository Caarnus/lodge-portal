<script lang="ts" setup>
import AppLayout from "@/layouts/AppLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import {Head, Link} from "@inertiajs/vue3";

defineOptions({layout: AppLayout});
defineProps<{ lodge: any; communication: any }>();
</script>
<template>
    <Head :title="communication.subject"/>
    <main class="mx-auto max-w-4xl space-y-6 p-4 md:p-6">
        <PageHeader :title="communication.subject">
            <template #actions>
                <Link
                    :href="`/lodges/${lodge.id}/communications`"
                    class="inline-flex items-center rounded-md border border-border bg-card px-3 py-2 text-sm font-medium hover:bg-accent"
                >
                    Back to communications
                </Link>
            </template>
        </PageHeader>
        <article
            class="public-rich-text rounded-lg border border-border bg-card p-5"
            v-html="communication.body_html"
        />
    </main>
</template>
