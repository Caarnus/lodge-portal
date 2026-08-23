<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
defineOptions({ layout: AppLayout });
const props = defineProps<{
    lodge: any;
    album: any;
    draft: any;
    media: any[];
    canPublish: boolean;
}>();
const form = useForm({
    title: props.draft.title,
    slug: props.album.slug,
    description: props.draft.description ?? "",
    visibility: props.draft.visibility,
    cover_photo_id: props.draft.cover_photo_id,
});
const add = useForm({ media_asset_id: null as number | null });
const upload = useForm<{ file: File | null; alt_text: string }>({
    file: null,
    alt_text: "",
});
</script>
<template>
    <Head :title="`Edit ${draft.title}`" />
    <main class="mx-auto max-w-5xl space-y-6 p-6">
        <header class="flex justify-between">
            <div>
                <Link
                    :href="`/lodges/${lodge.id}/galleries/manage`"
                    class="underline"
                    >← Galleries</Link
                >
                <h1 class="text-3xl font-bold">{{ draft.title }}</h1>
            </div>
            <button
                v-if="canPublish"
                class="rounded bg-slate-900 px-4 py-2 text-white"
                @click="
                    router.post(
                        `/lodges/${lodge.id}/galleries/manage/${album.id}/publish`,
                    )
                "
            >
                Publish
            </button>
        </header>
        <form
            class="grid gap-3 rounded border p-5"
            @submit.prevent="
                form.put(`/lodges/${lodge.id}/galleries/manage/${album.id}`)
            "
        >
            <input v-model="form.title" class="field-input" /><input
                v-model="form.slug"
                class="field-input"
            /><textarea v-model="form.description" class="field-input" /><select
                v-model="form.visibility"
                class="field-input"
            >
                <option value="public">Public</option>
                <option value="masons">All Masons</option>
                <option value="lodge">Lodge members</option></select
            ><select v-model.number="form.cover_photo_id" class="field-input">
                <option :value="null">Automatic cover</option>
                <option
                    v-for="photo in draft.photos"
                    :key="photo.id"
                    :value="photo.id"
                >
                    {{ photo.media_asset.original_name }}
                </option></select
            ><button class="w-fit rounded border px-4 py-2">Save draft</button>
        </form>
        <section class="rounded border p-5">
            <h2 class="font-semibold">Upload photo</h2>
            <form
                class="mt-3 flex flex-wrap gap-3"
                @submit.prevent="
                    upload.post(`/lodges/${lodge.id}/galleries/manage/media`, {
                        forceFormData: true,
                    })
                "
            >
                <input
                    required
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif"
                    @change="
                        upload.file =
                            ($event.target as HTMLInputElement).files?.[0] ??
                            null
                    "
                /><input
                    v-model="upload.alt_text"
                    required
                    placeholder="Alternative text"
                    class="field-input"
                /><button class="rounded border px-3">Upload</button>
            </form>
        </section>
        <section class="rounded border p-5">
            <h2 class="font-semibold">Photos</h2>
            <form
                class="mt-3 flex gap-3"
                @submit.prevent="
                    add.post(
                        `/lodges/${lodge.id}/galleries/manage/${album.id}/photos`,
                    )
                "
            >
                <select v-model.number="add.media_asset_id" class="field-input">
                    <option :value="null">Select media</option>
                    <option
                        v-for="item in media"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.original_name }}
                    </option></select
                ><button class="rounded border px-3">Add</button>
            </form>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <article v-for="photo in draft.photos" :key="photo.id">
                    <img
                        v-if="photo.media_asset.url"
                        :src="photo.media_asset.url"
                        :alt="photo.media_asset.alt_text"
                        class="aspect-square w-full object-cover"
                    /><input
                        :value="photo.caption"
                        class="field-input"
                        @change="
                            router.put(
                                `/lodges/${lodge.id}/galleries/manage/${album.id}/photos/${photo.id}`,
                                {
                                    caption: ($event.target as HTMLInputElement)
                                        .value,
                                },
                            )
                        "
                    /><button
                        class="mt-2 text-red-700 underline"
                        @click="
                            router.delete(
                                `/lodges/${lodge.id}/galleries/manage/${album.id}/photos/${photo.id}`,
                            )
                        "
                    >
                        Remove
                    </button>
                </article>
            </div>
        </section>
    </main>
</template>
