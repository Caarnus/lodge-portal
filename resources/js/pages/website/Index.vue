<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import MediaLibraryModal from "@/components/media/MediaLibraryModal.vue";
import { normalizeSlug } from "@/utils/slug";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import {
    Eye,
    FilePlus2,
    GripVertical,
    ImagePlus,
    Pencil,
    RotateCcw,
    Rocket,
    Trash2,
} from "lucide-vue-next";
import Tooltip from "primevue/tooltip";
import { computed, ref, watch } from "vue";

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
    is_navigation_container: false,
    navigation_visibility: "public",
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
const mediaOpen = ref(false);
const draggingPageId = ref<number | null>(null);
const dropTarget = ref<string | null>(null);
const localPages = ref<any[]>([]);
const clonePages = (pages: any[]) => JSON.parse(JSON.stringify(pages));
watch(
    () => props.pages,
    (pages) => (localPages.value = clonePages(pages)),
    { immediate: true },
);
watch(
    () => [createForm.is_home, createForm.show_in_navigation],
    ([isHome, showInNavigation]) => {
        if (isHome || !showInNavigation) {
            createForm.is_navigation_container = false;
        }
    },
);
watch(
    () => createForm.is_navigation_container,
    (isContainer) => {
        if (isContainer) {
            createForm.slug = "";
        }
    },
);
const pageVersion = (page: any) => page.draft ?? page.published;
const siblingPages = (parentId: number | null) =>
    localPages.value
        .filter(
            (page) =>
                (pageVersion(page)?.navigation_parent_page_id ?? null) ===
                parentId,
        )
        .sort(
            (left, right) =>
                (pageVersion(left)?.navigation_order ?? 0) -
                (pageVersion(right)?.navigation_order ?? 0),
        );
const pageRows = computed(() => {
    const rows: Array<{ page: any; depth: number }> = [];
    const visit = (
        parentId: number | null,
        depth: number,
        seen = new Set(),
    ) => {
        siblingPages(parentId).forEach((page) => {
            if (seen.has(page.id)) return;
            seen.add(page.id);
            rows.push({ page, depth });
            visit(page.id, depth + 1, seen);
        });
    };
    visit(null, 0);
    localPages.value.forEach((page) => {
        if (!rows.some((row) => row.page.id === page.id)) {
            rows.push({ page, depth: 0 });
        }
    });

    return rows;
});
const isDescendant = (pageId: number, possibleParentId: number | null) => {
    let parentId = possibleParentId;
    while (parentId !== null) {
        if (parentId === pageId) return true;
        parentId =
            pageVersion(localPages.value.find((page) => page.id === parentId))
                ?.navigation_parent_page_id ?? null;
    }

    return false;
};
const reorderTargetKey = (parentId: number | null, index: number) =>
    `reorder:${parentId ?? "root"}:${index}`;
const nestTargetKey = (pageId: number) => `nest:${pageId}`;
const beginDrag = (pageId: number) => {
    draggingPageId.value = pageId;
    dropTarget.value = null;
};
const finishDrag = () => {
    draggingPageId.value = null;
    dropTarget.value = null;
};
const setReorderTarget = (parentId: number | null, index: number) => {
    if (
        draggingPageId.value !== null &&
        !isDescendant(draggingPageId.value, parentId)
    ) {
        dropTarget.value = reorderTargetKey(parentId, index);
    }
};
const setNestTarget = (page: any) => {
    if (
        draggingPageId.value !== null &&
        !isDescendant(draggingPageId.value, page.id)
    ) {
        dropTarget.value = nestTargetKey(page.id);
    }
};
const movePage = (parentId: number | null, index: number) => {
    const pageId = draggingPageId.value;
    if (pageId === null || isDescendant(pageId, parentId)) return;
    const moved = localPages.value.find((page) => page.id === pageId);
    if (!moved) return;
    const sourceParentId = pageVersion(moved).navigation_parent_page_id ?? null;
    const sourceSiblings = siblingPages(sourceParentId).filter(
        (page) => page.id !== pageId,
    );
    const targetSiblings =
        sourceParentId === parentId
            ? sourceSiblings
            : siblingPages(parentId).filter((page) => page.id !== pageId);
    targetSiblings.splice(Math.min(index, targetSiblings.length), 0, moved);
    pageVersion(moved).navigation_parent_page_id = parentId;
    if (sourceParentId !== parentId) {
        sourceSiblings.forEach((page, order) => {
            pageVersion(page).navigation_order = order * 10;
        });
    }
    targetSiblings.forEach((page, order) => {
        pageVersion(page).navigation_order = order * 10;
    });
    finishDrag();
    router.put(
        `/lodges/${props.lodge.id}/website/pages/navigation`,
        {
            pages: localPages.value.map((page) => ({
                id: page.id,
                navigation_parent_page_id:
                    pageVersion(page).navigation_parent_page_id ?? null,
                navigation_order: pageVersion(page).navigation_order ?? 0,
            })),
        },
        {
            preserveScroll: true,
            onError: () => (localPages.value = clonePages(props.pages)),
        },
    );
};
const moveIntoPage = (page: any) =>
    movePage(page.id, siblingPages(page.id).length);
const parentIdFor = (page: any) =>
    pageVersion(page).navigation_parent_page_id ?? null;
const siblingIndex = (page: any) =>
    siblingPages(parentIdFor(page)).findIndex((item) => item.id === page.id);
const navigationBadge = (page: any) => {
    const version = pageVersion(page);
    if (!version?.show_in_navigation) return "Hidden from menu";

    const labels: Record<string, string> = {
        public: "Public menu",
        masons: "Masons menu",
        lodge: "Lodge menu",
    };

    return labels[version.navigation_visibility] ?? labels.public;
};
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
</script>

<template>
    <Head :title="`${lodge.name} website`" />
    <main class="mx-auto w-full max-w-6xl space-y-8 p-4 sm:p-6 lg:p-8">
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm text-slate-500">{{ lodge.name }}</p>
                <h1 class="text-3xl font-bold">Website</h1>
            </div>
            <div class="flex gap-2">
                <button class="secondary-button" @click="mediaOpen = true">
                    <ImagePlus class="mr-1 size-4" /> Media library
                </button>
                <a
                    :href="`/l/${lodge.slug}`"
                    target="_blank"
                    class="secondary-button"
                    >View public site</a
                >
            </div>
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
        </section>

        <section class="rounded-lg border p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">Pages</h2>
                    <p class="text-sm text-slate-500">
                        Drag a page onto another page to nest it. Use the gaps
                        to reorder. Draft changes stay private until published.
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
            <div class="mt-4 overflow-hidden rounded-md border">
                <template v-for="row in pageRows" :key="row.page.id">
                    <div
                        class="flex items-center justify-center overflow-hidden transition-all"
                        :class="
                            dropTarget ===
                            reorderTargetKey(
                                parentIdFor(row.page),
                                siblingIndex(row.page),
                            )
                                ? 'h-9 bg-primary/15 text-primary ring-1 ring-inset ring-primary/40'
                                : 'h-2 hover:bg-primary/20'
                        "
                        @dragenter.prevent="
                            setReorderTarget(
                                parentIdFor(row.page),
                                siblingIndex(row.page),
                            )
                        "
                        @dragover.prevent
                        @drop.stop="
                            movePage(
                                parentIdFor(row.page),
                                siblingIndex(row.page),
                            )
                        "
                    >
                        <span
                            v-if="
                                dropTarget ===
                                reorderTargetKey(
                                    parentIdFor(row.page),
                                    siblingIndex(row.page),
                                )
                            "
                            class="text-xs font-semibold"
                        >
                            Drop here to reorder
                        </span>
                    </div>
                    <article
                        draggable="true"
                        class="flex min-w-0 items-center gap-3 border-y border-transparent p-3 transition-colors"
                        :class="{
                            'border-primary/50 bg-primary/5':
                                draggingPageId === row.page.id,
                            'border-primary bg-primary/10 ring-1 ring-inset ring-primary/40':
                                dropTarget === nestTargetKey(row.page.id),
                        }"
                        :style="{ paddingLeft: `${0.75 + row.depth * 1.5}rem` }"
                        @dragstart="beginDrag(row.page.id)"
                        @dragend="finishDrag"
                        @dragenter.prevent="setNestTarget(row.page)"
                        @dragover.prevent
                        @drop.stop="moveIntoPage(row.page)"
                    >
                        <GripVertical
                            class="size-4 shrink-0 cursor-grab text-muted-foreground"
                            aria-hidden="true"
                        />
                        <div class="min-w-0 flex-1">
                            <div
                                class="flex min-w-0 flex-wrap items-center gap-2"
                            >
                                <h3 class="truncate font-medium">
                                    {{
                                        row.page.draft?.title ??
                                        row.page.published?.title
                                    }}
                                </h3>
                                <span
                                    class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                                >
                                    {{ navigationBadge(row.page) }}
                                </span>
                                <span
                                    v-if="
                                        pageVersion(row.page)
                                            ?.is_navigation_container
                                    "
                                    class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                                >
                                    Container
                                </span>
                                <span
                                    v-if="
                                        dropTarget ===
                                        nestTargetKey(row.page.id)
                                    "
                                    class="rounded-full bg-primary px-2 py-0.5 text-xs font-semibold text-primary-foreground"
                                >
                                    Drop to nest here
                                </span>
                            </div>
                            <p
                                v-if="
                                    !pageVersion(row.page)
                                        ?.is_navigation_container
                                "
                                class="truncate text-sm text-slate-500"
                            >
                                /{{
                                    row.page.draft?.slug ??
                                    row.page.published?.slug
                                }}
                                ·
                                <span v-if="row.page.draft">Draft</span
                                ><span
                                    v-if="row.page.draft && row.page.published"
                                >
                                    +
                                </span>
                                ><span v-if="row.page.published"
                                    >Published</span
                                >
                            </p>
                        </div>
                        <a
                            v-if="row.page.published"
                            :href="
                                row.page.published.is_home
                                    ? `/l/${lodge.slug}`
                                    : `/l/${lodge.slug}/${row.page.published.slug}`
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
                            :href="`/lodges/${lodge.id}/website/pages/${row.page.id}/edit`"
                            aria-label="Edit page"
                            class="icon-button"
                            v-tooltip.top="{
                                value: 'Edit page',
                                showDelay: 2000,
                            }"
                            ><Pencil class="size-4"
                        /></Link>
                        <button
                            v-if="canPublish && row.page.draft"
                            aria-label="Publish page"
                            class="icon-button"
                            v-tooltip.top="{
                                value: 'Publish page',
                                showDelay: 2000,
                            }"
                            @click="publish(row.page)"
                        >
                            <Rocket class="size-4" />
                        </button>
                        <button
                            v-if="
                                canPublish &&
                                row.page.published &&
                                !row.page.published.is_home
                            "
                            aria-label="Unpublish page"
                            class="icon-button"
                            v-tooltip.top="{
                                value: 'Unpublish page',
                                showDelay: 2000,
                            }"
                            @click="unpublish(row.page)"
                        >
                            <Rocket class="size-4 rotate-180" />
                        </button>
                        <button
                            v-if="!row.page.published?.is_home"
                            aria-label="Delete page"
                            class="icon-button text-red-600"
                            v-tooltip.top="{
                                value: 'Delete page',
                                showDelay: 2000,
                            }"
                            @click="remove(row.page)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </article>
                </template>
                <div
                    class="flex items-center justify-center overflow-hidden transition-all"
                    :class="
                        dropTarget ===
                        reorderTargetKey(null, siblingPages(null).length)
                            ? 'h-9 bg-primary/15 text-primary ring-1 ring-inset ring-primary/40'
                            : 'h-2 hover:bg-primary/20'
                    "
                    @dragenter.prevent="
                        setReorderTarget(null, siblingPages(null).length)
                    "
                    @dragover.prevent
                    @drop.stop="movePage(null, siblingPages(null).length)"
                >
                    <span
                        v-if="
                            dropTarget ===
                            reorderTargetKey(null, siblingPages(null).length)
                        "
                        class="text-xs font-semibold"
                    >
                        Drop here to move to the end
                    </span>
                </div>
                <p
                    v-if="localPages.length === 0"
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
                        :disabled="createForm.is_navigation_container"
                        :required="!createForm.is_navigation_container"
                        pattern="[A-Za-z0-9_-]+"
                        class="mt-1 w-full rounded-md border px-3 py-2"
                /></label>
                <div class="grid gap-4 sm:col-span-2 sm:grid-cols-2">
                    <div class="grid gap-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <label class="field-toggle w-fit"
                                ><input
                                    v-model="createForm.is_home"
                                    type="checkbox"
                                />
                                Home page</label
                            >
                            <label class="field-toggle w-fit"
                                ><input
                                    v-model="createForm.show_in_navigation"
                                    type="checkbox"
                                />
                                Show in navigation</label
                            >
                            <label
                                class="field-toggle w-fit"
                                :class="{
                                    'opacity-50':
                                        !createForm.show_in_navigation ||
                                        createForm.is_home,
                                }"
                                ><input
                                    v-model="createForm.is_navigation_container"
                                    type="checkbox"
                                    :disabled="
                                        !createForm.show_in_navigation ||
                                        createForm.is_home
                                    "
                                />
                                Nav container</label
                            >
                        </div>
                        <fieldset
                            class="rounded-lg border border-border p-3 transition-opacity"
                            :class="{
                                'bg-muted/40 opacity-50':
                                    !createForm.show_in_navigation,
                            }"
                            :disabled="!createForm.show_in_navigation"
                        >
                            <legend class="px-1 text-sm font-medium">
                                Navigation visibility
                            </legend>
                            <div class="flex flex-wrap gap-x-4 gap-y-2 text-sm">
                                <label class="flex items-center gap-2"
                                    ><input
                                        v-model="
                                            createForm.navigation_visibility
                                        "
                                        type="radio"
                                        value="public"
                                    />
                                    All visitors</label
                                >
                                <label class="flex items-center gap-2"
                                    ><input
                                        v-model="
                                            createForm.navigation_visibility
                                        "
                                        type="radio"
                                        value="masons"
                                    />
                                    Masons</label
                                >
                                <label class="flex items-center gap-2"
                                    ><input
                                        v-model="
                                            createForm.navigation_visibility
                                        "
                                        type="radio"
                                        value="lodge"
                                    />
                                    Lodge members</label
                                >
                            </div>
                        </fieldset>
                    </div>
                    <div class="flex items-end justify-end">
                        <button
                            class="primary-button"
                            :disabled="createForm.processing"
                        >
                            Create page
                        </button>
                    </div>
                </div>
                <p
                    v-if="Object.keys(createForm.errors).length"
                    class="text-sm text-red-600 sm:col-span-2"
                >
                    {{ Object.values(createForm.errors)[0] }}
                </p>
            </form>
        </section>
    </main>
    <MediaLibraryModal
        :open="mediaOpen"
        :lodge="lodge"
        :media="media"
        @update:open="mediaOpen = $event"
    />
</template>
