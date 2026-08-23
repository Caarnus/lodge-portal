<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import RichTextField from "@/components/website/RichTextField.vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
defineOptions({ layout: AppLayout });
const props = defineProps<{ lodge: any; communication: any }>();
const form = useForm({
    subject: props.communication.subject,
    body_html: props.communication.body_html,
});
</script>
<template>
    <Head :title="communication.subject" />
    <main class="mx-auto max-w-4xl space-y-6 p-6">
        <Link
            :href="`/lodges/${lodge.id}/communications/manage`"
            class="underline"
            >← Communications</Link
        >
        <form
            class="grid gap-4 rounded border p-5"
            @submit.prevent="
                form.put(
                    `/lodges/${lodge.id}/communications/manage/${communication.id}`,
                )
            "
        >
            <input
                v-model="form.subject"
                required
                class="field-input"
            /><RichTextField v-model="form.body_html" /><button
                v-if="communication.status === 'draft'"
                class="w-fit rounded bg-slate-900 px-4 py-2 text-white"
            >
                Save draft
            </button>
        </form>
        <button
            v-if="communication.status === 'draft'"
            class="rounded border px-4 py-2"
            @click="
                router.post(
                    `/lodges/${lodge.id}/communications/manage/${communication.id}/send`,
                )
            "
        >
            Send to eligible members
        </button>
    </main>
</template>
