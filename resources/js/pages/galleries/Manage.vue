<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
defineOptions({ layout: AppLayout });
const props = defineProps<{ lodge: any; albums: any[]; canPublish: boolean }>();
const form = useForm({
    title: "",
    slug: "",
    description: "",
    visibility: "public",
    cover_photo_id: null as number | null,
});
</script>
<template>
    <Head title="Galleries" />
    <main class="mx-auto max-w-5xl space-y-6 p-6">
        <h1 class="text-3xl font-bold">Galleries</h1>
        <div class="divide-y rounded border">
            <article
                v-for="album in albums"
                :key="album.id"
                class="flex gap-3 p-4"
            >
                <div class="flex-1">
                    <strong>{{
                        album.draft?.title ?? album.published?.title
                    }}</strong>
                    <p class="text-sm">
                        {{ album.draft ? "Draft" : "" }}
                        {{ album.published ? "Published" : "" }}
                    </p>
                </div>
                <Link
                    :href="`/lodges/${lodge.id}/galleries/manage/${album.id}/edit`"
                    class="underline"
                    >Edit</Link
                ><button
                    v-if="canPublish && album.published"
                    class="underline"
                    @click="
                        router.post(
                            `/lodges/${lodge.id}/galleries/manage/${album.id}/unpublish`,
                        )
                    "
                >
                    Unpublish
                </button>
            </article>
        </div>
        <section class="rounded border p-5">
            <h2 class="text-xl font-semibold">Create album</h2>
            <form
                class="mt-3 grid gap-3"
                @submit.prevent="
                    form.post(`/lodges/${lodge.id}/galleries/manage`)
                "
            >
                <input
                    v-model="form.title"
                    required
                    placeholder="Title"
                    class="field-input"
                /><input
                    v-model="form.slug"
                    required
                    placeholder="Slug"
                    class="field-input"
                /><select v-model="form.visibility" class="field-input">
                    <option value="public">Public</option>
                    <option value="masons">All Masons</option>
                    <option value="lodge">Lodge members</option></select
                ><button
                    class="w-fit rounded bg-slate-900 px-4 py-2 text-white"
                >
                    Create draft
                </button>
            </form>
        </section>
    </main>
</template>
