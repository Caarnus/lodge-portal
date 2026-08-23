<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import { Head, Link } from "@inertiajs/vue3";
defineOptions({ layout: AppLayout });
defineProps<{ lodge: any; communications: any[] }>();
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
                <p class="text-sm">{{ item.sent_at }}</p></Link
            >
        </div>
    </main>
</template>
