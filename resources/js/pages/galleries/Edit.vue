<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import WorkspaceTabs from "@/components/WorkspaceTabs.vue";
import { normalizeSlug } from "@/utils/slug";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { ImagePlus } from "lucide-vue-next";
import { ref } from "vue";
defineOptions({ layout: AppLayout });
const props = defineProps<{
    lodge: any;
    album: any;
    draft: any;
    media: any[];
    canPublish: boolean;
    embedded?: boolean;
}>();
const emit = defineEmits<{ saved: []; openMedia: [] }>();
const form = useForm({
    title: props.draft.title,
    slug: props.album.slug,
    description: props.draft.description ?? "",
    visibility: props.draft.visibility,
    cover_photo_id: props.draft.cover_photo_id,
});
const add = useForm({ media_asset_id: null as number | null });
const publishError = ref("");
const publish = () => {
    publishError.value = "";
    router.post(
        `/lodges/${props.lodge.id}/galleries/manage/${props.album.id}/publish`,
        {},
        {
            onError: (errors) => {
                publishError.value =
                    errors.photos ?? "Gallery could not be published.";
            },
        },
    );
};
const save = () =>
    form.put(`/lodges/${props.lodge.id}/galleries/manage/${props.album.id}`, {
        onSuccess: () => emit("saved"),
    });
</script>
<template>
    <Head :title="`Edit ${draft.title}`" />
    <main
        :class="
            embedded
                ? 'w-full min-w-0 space-y-5'
                : 'mx-auto w-full min-w-0 max-w-5xl space-y-5 p-4 md:p-6'
        "
    >
        <PageHeader
            v-if="!embedded"
            :title="draft.title"
            description="Gallery editor"
        >
            <template #eyebrow>
                <Link :href="`/lodges/${lodge.id}/galleries/manage`">
                    Media galleries
                </Link>
            </template>
            <template #actions>
                <button
                    v-if="canPublish"
                    type="button"
                    class="primary-button"
                    @click="publish"
                >
                    Publish
                </button>
            </template>
        </PageHeader>
        <WorkspaceTabs
            v-if="!embedded"
            :lodge="lodge"
            workspace="content"
            active="galleries"
        />
        <header v-else class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <Link
                    v-if="!embedded"
                    :href="`/lodges/${lodge.id}/galleries/manage`"
                    class="text-sm font-medium underline"
                    >← Galleries</Link
                >
                <h1 class="text-2xl font-semibold">{{ draft.title }}</h1>
            </div>
            <button
                v-if="canPublish"
                type="button"
                class="primary-button"
                @click="publish"
            >
                Publish
            </button>
        </header>
        <p
            v-if="publishError"
            class="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive"
            role="alert"
        >
            {{ publishError }}
        </p>
        <form
            class="grid gap-4 rounded-lg border border-border bg-card p-4 md:grid-cols-2 md:p-5"
            @submit.prevent="save"
        >
            <h2 class="text-lg font-medium md:col-span-2">Gallery details</h2>
            <label class="field-label md:col-span-2">
                Title
                <input v-model="form.title" required class="field-input" />
            </label>
            <label class="field-label">
                Slug
                <input
                    v-model="form.slug"
                    required
                    class="field-input"
                    @input="form.slug = normalizeSlug(form.slug)"
                />
            </label>
            <label class="field-label">
                Visibility
                <select v-model="form.visibility" class="field-input">
                    <option value="public">Public</option>
                    <option value="masons">All Masons</option>
                    <option value="lodge">Lodge members</option>
                </select>
            </label>
            <label class="field-label md:col-span-2">
                Description
                <textarea
                    v-model="form.description"
                    class="field-input min-h-28"
                />
            </label>
            <label class="field-label md:col-span-2">
                Cover photo
                <select
                    v-model.number="form.cover_photo_id"
                    class="field-input"
                >
                    <option :value="null">Use first photo automatically</option>
                    <option
                        v-for="photo in draft.photos"
                        :key="photo.id"
                        :value="photo.id"
                    >
                        {{ photo.media_asset.original_name }}
                    </option>
                </select>
            </label>
            <div class="flex justify-end md:col-span-2">
                <button class="primary-button" :disabled="form.processing">
                    Save draft
                </button>
            </div>
        </form>
        <section class="rounded-lg border border-border bg-card p-4 md:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-medium">Gallery photos</h2>
                <div
                    class="flex w-full flex-wrap gap-2 md:w-auto md:flex-nowrap"
                >
                    <button
                        type="button"
                        class="secondary-button"
                        @click="emit('openMedia')"
                    >
                        <ImagePlus class="mr-1 size-4" /> Media library
                    </button>
                    <form
                        class="flex min-w-0 flex-1 gap-2 md:flex-none"
                        @submit.prevent="
                            add.post(
                                `/lodges/${lodge.id}/galleries/manage/${album.id}/photos`,
                            )
                        "
                    >
                        <select
                            v-model.number="add.media_asset_id"
                            class="field-input min-w-0"
                        >
                            <option :value="null">Add existing media</option>
                            <option
                                v-for="item in media"
                                :key="item.id"
                                :value="item.id"
                            >
                                {{ item.original_name }}
                            </option>
                        </select>
                        <button
                            class="secondary-button"
                            :disabled="add.processing"
                        >
                            Add
                        </button>
                    </form>
                </div>
            </div>
            <div
                v-if="draft.photos.length"
                class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <article
                    v-for="photo in draft.photos"
                    :key="photo.id"
                    class="overflow-hidden rounded-lg border border-border"
                >
                    <img
                        v-if="photo.media_asset.url"
                        :src="photo.media_asset.url"
                        :alt="photo.media_asset.alt_text"
                        class="aspect-square w-full object-cover"
                    />
                    <div class="grid gap-2 p-3">
                        <input
                            :value="photo.caption"
                            class="field-input"
                            placeholder="Caption"
                            @change="
                                router.put(
                                    `/lodges/${lodge.id}/galleries/manage/${album.id}/photos/${photo.id}`,
                                    {
                                        caption: (
                                            $event.target as HTMLInputElement
                                        ).value,
                                    },
                                )
                            "
                        />
                        <div class="flex justify-end">
                            <button
                                type="button"
                                class="secondary-button border-destructive/50 text-destructive hover:bg-destructive/10"
                                @click="
                                    router.delete(
                                        `/lodges/${lodge.id}/galleries/manage/${album.id}/photos/${photo.id}`,
                                    )
                                "
                            >
                                Remove
                            </button>
                        </div>
                    </div>
                </article>
            </div>
            <p
                v-else
                class="mt-4 rounded-md border border-dashed p-4 text-sm text-muted-foreground"
            >
                Add a ready image from Media library.
            </p>
        </section>
    </main>
</template>
