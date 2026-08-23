<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
defineOptions({ layout: AppLayout });
const props = defineProps<{ lodge: any; communications: any[] }>();
const form = useForm({ subject: "", body_html: "<p></p>" });
</script>
<template>
    <Head title="Communications" />
    <main class="mx-auto max-w-5xl space-y-6 p-6">
        <h1 class="text-3xl font-bold">Lodge communications</h1>
        <div class="divide-y rounded border">
            <article
                v-for="item in communications"
                :key="item.id"
                class="flex gap-3 p-4"
            >
                <div class="flex-1">
                    <strong>{{ item.subject }}</strong>
                    <p class="text-sm">{{ item.status }}</p>
                </div>
                <Link
                    :href="`/lodges/${lodge.id}/communications/manage/${item.id}/edit`"
                    class="underline"
                    >Edit</Link
                >
            </article>
        </div>
        <form
            class="grid gap-3 rounded border p-5"
            @submit.prevent="
                form.post(`/lodges/${lodge.id}/communications/manage`)
            "
        >
            <input
                v-model="form.subject"
                required
                placeholder="Subject"
                class="field-input"
            /><button class="w-fit rounded bg-slate-900 px-4 py-2 text-white">
                Create draft
            </button>
        </form>
    </main>
</template>
