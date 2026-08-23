<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import { normalizeSlug } from "@/utils/slug";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import {
    Eye,
    FilePlus2,
    ImagePlus,
    Pencil,
    RotateCcw,
    Rocket,
    Trash2,
} from "lucide-vue-next";
import Tooltip from "primevue/tooltip";

const vTooltip = Tooltip;
defineOptions({ layout: AppLayout });

const props = defineProps<{
    lodge: any;
    pages: any[];
    deletedPages: any[];
    media: any[];
    canPublish: boolean;
    sectionTypes: Record<string, string>;
}>();
const createForm = useForm({
    title: "",
    slug: "",
    is_home: false,
    show_in_navigation: true,
    navigation_order: 0,
    navigation_parent_page_id: null as number | null,
});
const branding = useForm({
    tag_line: props.lodge.tag_line ?? "",
    primary_color: props.lodge.primary_color,
    secondary_color: props.lodge.secondary_color,
    logo_media_id: null as number | null,
    seal_media_id: null as number | null,
});
const upload = useForm<{ file: File | null; alt_text: string }>({
    file: null,
    alt_text: "",
});
const submitPage = () =>
    createForm.post(`/lodges/${props.lodge.id}/website/pages`);
const applyTemplate = () =>
    router.post(`/lodges/${props.lodge.id}/website/template`);
const unpublish = (page: any) =>
    router.post(`/lodges/${props.lodge.id}/website/pages/${page.id}/unpublish`);
const publish = (page: any) =>
    router.post(`/lodges/${props.lodge.id}/website/pages/${page.id}/publish`);
const remove = (page: any) =>
    router.delete(`/lodges/${props.lodge.id}/website/pages/${page.id}`);
const restore = (page: any) =>
    router.post(
        `/lodges/${props.lodge.id}/website/deleted-pages/${page.id}/restore`,
    );
const sendUpload = () =>
    upload.post(`/lodges/${props.lodge.id}/website/media`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => upload.reset(),
    });
</script>

<template>
    <Head :title="`${lodge.name} website`" />
    <main class="mx-auto w-full max-w-6xl space-y-8 p-4 sm:p-6 lg:p-8">
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm text-slate-500">{{ lodge.name }}</p>
                <h1 class="text-3xl font-bold">Website</h1>
            </div>
            <a
                :href="`/l/${lodge.slug}`"
                target="_blank"
                class="rounded-md border px-4 py-2 text-sm font-medium hover:bg-slate-50"
                >View public site</a
            >
        </header>

        <section class="rounded-lg border p-5">
            <h2 class="text-lg font-semibold">Branding</h2>
            <form
                class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                @submit.prevent="
                    branding.put(`/lodges/${lodge.id}/website/branding`)
                "
            >
                <label class="text-sm font-medium"
                    >Tag line<input
                        v-model="branding.tag_line"
                        class="mt-1 w-full rounded-md border px-3 py-2"
                        maxlength="255"
                /></label>
                <label class="text-sm font-medium"
                    >Primary<input
                        v-model="branding.primary_color"
                        type="color"
                        class="mt-1 h-10 w-full rounded border"
                /></label>
                <label class="text-sm font-medium"
                    >Secondary<input
                        v-model="branding.secondary_color"
                        type="color"
                        class="mt-1 h-10 w-full rounded border"
                /></label>
                <label class="text-sm font-medium"
                    >Logo<select
                        v-model.number="branding.logo_media_id"
                        class="mt-1 w-full rounded-md border px-3 py-2"
                    >
                        <option :value="null">Keep current</option>
                        <option
                            v-for="asset in media.filter(
                                (item) => item.processing_status === 'ready',
                            )"
                            :key="asset.id"
                            :value="asset.id"
                        >
                            {{ asset.original_name }}
                        </option>
                    </select></label
                >
                <label class="text-sm font-medium"
                    >Seal<select
                        v-model.number="branding.seal_media_id"
                        class="mt-1 w-full rounded-md border px-3 py-2"
                    >
                        <option :value="null">Keep current</option>
                        <option
                            v-for="asset in media.filter(
                                (item) => item.processing_status === 'ready',
                            )"
                            :key="asset.id"
                            :value="asset.id"
                        >
                            {{ asset.original_name }}
                        </option>
                    </select></label
                >
                <button
                    class="self-end rounded-md bg-slate-900 px-4 py-2 text-white disabled:opacity-50"
                    :disabled="branding.processing"
                >
                    Save branding
                </button>
            </form>
            <form
                class="mt-5 grid gap-3 border-t pt-5 sm:grid-cols-[1fr_1fr_auto]"
                @submit.prevent="sendUpload"
            >
                <label class="field-label"
                    >Upload branding image<input
                        required
                        type="file"
                        accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif"
                        class="field-input"
                        @change="
                            upload.file =
                                ($event.target as HTMLInputElement)
                                    .files?.[0] ?? null
                        " /></label
                ><label class="field-label"
                    >Alternative text<input
                        v-model="upload.alt_text"
                        required
                        class="field-input" /></label
                ><button
                    class="inline-flex self-end items-center gap-2 rounded-md border px-4 py-2"
                >
                    <ImagePlus class="size-4" /> Upload
                </button>
            </form>
        </section>

        <section class="rounded-lg border p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">Pages</h2>
                    <p class="text-sm text-slate-500">
                        Draft changes stay private until published.
                    </p>
                </div>
                <button
                    v-if="pages.length === 0"
                    class="rounded-md border px-4 py-2 text-sm font-medium"
                    @click="applyTemplate"
                >
                    Apply default template
                </button>
            </div>
            <div class="mt-4 divide-y rounded-md border">
                <article
                    v-for="page in pages"
                    :key="page.id"
                    class="flex min-w-0 items-center gap-3 p-3"
                >
                    <div class="min-w-0 flex-1">
                        <h3 class="truncate font-medium">
                            {{ page.draft?.title ?? page.published?.title }}
                        </h3>
                        <p class="truncate text-sm text-slate-500">
                            /{{ page.draft?.slug ?? page.published?.slug }} ·
                            <span v-if="page.draft">Draft</span
                            ><span v-if="page.draft && page.published"> + </span
                            ><span v-if="page.published">Published</span>
                        </p>
                    </div>
                    <a
                        v-if="page.published"
                        :href="
                            page.published.is_home
                                ? `/l/${lodge.slug}`
                                : `/l/${lodge.slug}/${page.published.slug}`
                        "
                        target="_blank"
                        aria-label="View published page"
                        class="icon-button"
                        v-tooltip.top="{
                            value: 'View published page',
                            showDelay: 2000,
                        }"
                        ><Eye class="size-4"
                    /></a>
                    <Link
                        :href="`/lodges/${lodge.id}/website/pages/${page.id}/edit`"
                        aria-label="Edit page"
                        class="icon-button"
                        v-tooltip.top="{ value: 'Edit page', showDelay: 2000 }"
                        ><Pencil class="size-4"
                    /></Link>
                    <button
                        v-if="canPublish && page.draft"
                        aria-label="Publish page"
                        class="icon-button"
                        v-tooltip.top="{
                            value: 'Publish page',
                            showDelay: 2000,
                        }"
                        @click="publish(page)"
                    >
                        <Rocket class="size-4" />
                    </button>
                    <button
                        v-if="
                            canPublish &&
                            page.published &&
                            !page.published.is_home
                        "
                        aria-label="Unpublish page"
                        class="icon-button"
                        v-tooltip.top="{
                            value: 'Unpublish page',
                            showDelay: 2000,
                        }"
                        @click="unpublish(page)"
                    >
                        <Rocket class="size-4 rotate-180" />
                    </button>
                    <button
                        v-if="!page.published?.is_home"
                        aria-label="Delete page"
                        class="icon-button text-red-600"
                        v-tooltip.top="{
                            value: 'Delete page',
                            showDelay: 2000,
                        }"
                        @click="remove(page)"
                    >
                        <Trash2 class="size-4" />
                    </button>
                </article>
                <p
                    v-if="pages.length === 0"
                    class="p-6 text-center text-sm text-slate-500"
                >
                    No pages yet. Apply template or create one below.
                </p>
            </div>
            <div v-if="deletedPages.length" class="mt-5">
                <h3 class="text-sm font-semibold text-slate-600">
                    Deleted pages
                </h3>
                <div class="mt-2 divide-y rounded-md border">
                    <article
                        v-for="page in deletedPages"
                        :key="page.id"
                        class="flex items-center gap-3 p-3"
                    >
                        <span class="min-w-0 flex-1 truncate">{{
                            page.versions[0]?.title ?? `Page ${page.id}`
                        }}</span
                        ><button
                            class="icon-button"
                            aria-label="Restore page"
                            v-tooltip.top="{
                                value: 'Restore page',
                                showDelay: 2000,
                            }"
                            @click="restore(page)"
                        >
                            <RotateCcw class="size-4" />
                        </button>
                    </article>
                </div>
            </div>
        </section>

        <section class="rounded-lg border p-5">
            <h2 class="flex items-center gap-2 text-lg font-semibold">
                <FilePlus2 class="size-5" /> Create page
            </h2>
            <form
                class="mt-4 grid gap-4 sm:grid-cols-2"
                @submit.prevent="submitPage"
            >
                <label class="text-sm font-medium"
                    >Title<input
                        v-model="createForm.title"
                        required
                        class="mt-1 w-full rounded-md border px-3 py-2"
                /></label>
                <label class="text-sm font-medium"
                    >Slug<input
                        v-model="createForm.slug"
                        @input="
                            createForm.slug = normalizeSlug(createForm.slug)
                        "
                        required
                        pattern="[A-Za-z0-9_-]+"
                        class="mt-1 w-full rounded-md border px-3 py-2"
                /></label>
                <label class="flex items-center gap-2 text-sm"
                    ><input v-model="createForm.is_home" type="checkbox" /> Home
                    page</label
                >
                <label class="flex items-center gap-2 text-sm"
                    ><input
                        v-model="createForm.show_in_navigation"
                        type="checkbox"
                    />
                    Show in navigation</label
                >
                <p
                    v-if="Object.keys(createForm.errors).length"
                    class="text-sm text-red-600 sm:col-span-2"
                >
                    {{ Object.values(createForm.errors)[0] }}
                </p>
                <button
                    class="w-fit rounded-md bg-slate-900 px-4 py-2 text-white disabled:opacity-50"
                    :disabled="createForm.processing"
                >
                    Create page
                </button>
            </form>
        </section>
    </main>
</template>
