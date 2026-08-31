<script setup lang="ts">
import {
    Dialog,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from "@/components/ui/dialog";
import AppLayout from "@/layouts/AppLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import WorkspaceTabs from "@/components/WorkspaceTabs.vue";
import MediaLibraryModal from "@/components/media/MediaLibraryModal.vue";
import GalleryEditor from "@/pages/galleries/Edit.vue";
import { normalizeSlug } from "@/utils/slug";
import { Head, router, useForm } from "@inertiajs/vue3";
import {
    Eye,
    ImagePlus,
    Pencil,
    Plus,
    Rocket,
    Search,
    Trash2,
} from "lucide-vue-next";
import { computed, ref } from "vue";

defineOptions({ layout: AppLayout });
const props = defineProps<{
    lodge: any;
    albums: any[];
    media: any[];
    canPublish: boolean;
}>();
const query = ref("");
const creating = ref(false);
const editing = ref<any | null>(null);
const mediaOpen = ref(false);
const publishError = ref("");
const form = useForm({
    title: "",
    slug: "",
    description: "",
    visibility: "public",
    cover_photo_id: null as number | null,
});
const albums = computed(() =>
    props.albums.filter((album) =>
        (album.draft?.title ?? album.published?.title ?? "")
            .toLowerCase()
            .includes(query.value.toLowerCase()),
    ),
);
const create = () =>
    form.post(`/lodges/${props.lodge.id}/galleries/manage`, {
        onSuccess: () => {
            creating.value = false;
            form.reset();
        },
    });
const publish = (album: any) => {
    publishError.value = "";
    router.post(
        `/lodges/${props.lodge.id}/galleries/manage/${album.id}/publish`,
        {},
        {
            onError: (errors) => {
                publishError.value =
                    errors.photos ?? "Gallery could not be published.";
            },
        },
    );
};
const unpublish = (album: any) =>
    router.post(
        `/lodges/${props.lodge.id}/galleries/manage/${album.id}/unpublish`,
    );
const remove = (album: any) =>
    router.delete(`/lodges/${props.lodge.id}/galleries/manage/${album.id}`);
</script>

<template>
    <Head title="Galleries" />
    <main class="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-6">
        <PageHeader
            title="Media galleries"
            description="Draft galleries stay private until published."
        >
            <template #actions>
                <div class="flex flex-wrap gap-2">
                    <button class="secondary-button" @click="mediaOpen = true">
                        <ImagePlus class="mr-1 size-4" /> Media library
                    </button>
                    <button class="primary-button" @click="creating = true">
                        <Plus class="mr-1 size-4" /> New gallery
                    </button>
                </div>
            </template>
        </PageHeader>
        <WorkspaceTabs :lodge="lodge" workspace="content" active="galleries" />
        <p
            v-if="publishError"
            class="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive"
            role="alert"
        >
            {{ publishError }}
        </p>
        <div class="rounded-lg border border-border/80 bg-card p-4">
            <label class="relative block"
                ><Search
                    class="absolute left-3 top-3 size-4 text-muted-foreground" /><input
                    v-model="query"
                    type="search"
                    class="field-input pl-9"
                    placeholder="Search galleries"
            /></label>
        </div>
        <div
            v-if="albums.length"
            class="hidden overflow-hidden rounded-lg border border-border/80 bg-card md:block"
        >
            <table class="w-full table-fixed text-left text-sm">
                <colgroup>
                    <col />
                    <col class="w-16" />
                    <col class="w-24" />
                    <col class="w-20" />
                    <col class="w-40" />
                </colgroup>
                <thead class="border-b bg-muted/40">
                    <tr>
                        <th class="p-3 font-medium text-muted-foreground">
                            Title
                        </th>
                        <th
                            class="p-3 text-right font-medium text-muted-foreground"
                        >
                            Images
                        </th>
                        <th class="p-3 font-medium text-muted-foreground">
                            Visibility
                        </th>
                        <th class="p-3 font-medium text-muted-foreground">
                            Status
                        </th>
                        <th class="p-3">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="album in albums"
                        :key="album.id"
                        class="border-b border-border/60 transition-colors last:border-0 hover:bg-muted/35"
                    >
                        <td class="min-w-0 p-3 font-medium">
                            <span
                                class="block truncate"
                                :title="
                                    album.draft?.title ?? album.published?.title
                                "
                            >
                                {{
                                    album.draft?.title ?? album.published?.title
                                }}
                            </span>
                        </td>
                        <td class="p-3 text-right">
                            {{
                                album.draft?.photos?.length ??
                                album.published?.photos?.length ??
                                0
                            }}
                        </td>
                        <td class="p-3 capitalize">
                            {{
                                album.draft?.visibility ??
                                album.published?.visibility
                            }}
                        </td>
                        <td class="p-3">
                            {{ album.draft ? "Draft" : ""
                            }}{{ album.draft && album.published ? " + " : ""
                            }}{{ album.published ? "Published" : "" }}
                        </td>
                        <td class="px-2 py-3 text-right">
                            <span
                                class="inline-flex min-w-32 items-center justify-end gap-1"
                                ><a
                                    v-if="album.published"
                                    :href="`/l/${lodge.slug}/galleries/${album.slug}`"
                                    target="_blank"
                                    class="icon-button"
                                    title="View gallery"
                                    ><Eye class="size-4" /></a
                                ><button
                                    v-else
                                    class="icon-button text-destructive"
                                    title="Delete gallery"
                                    @click="remove(album)"
                                >
                                    <Trash2 class="size-4" /></button
                                ><button
                                    class="icon-button"
                                    title="Edit gallery"
                                    @click="editing = album"
                                >
                                    <Pencil class="size-4" /></button
                                ><button
                                    v-if="canPublish && album.draft"
                                    class="icon-button"
                                    title="Publish gallery"
                                    @click="publish(album)"
                                >
                                    <Rocket class="size-4" /></button
                                ><button
                                    v-else-if="canPublish && album.published"
                                    class="icon-button"
                                    title="Unpublish gallery"
                                    @click="unpublish(album)"
                                >
                                    <Rocket class="size-4 rotate-180" /></button
                                ><span v-else class="inline-block size-10"
                            /></span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="albums.length" class="space-y-3 md:hidden">
            <article
                v-for="album in albums"
                :key="album.id"
                class="rounded-lg border border-border/80 bg-card p-4"
            >
                <h2 class="font-medium">
                    {{ album.draft?.title ?? album.published?.title }}
                </h2>
                <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <dt class="text-muted-foreground">Visibility</dt>
                        <dd class="capitalize">
                            {{
                                album.draft?.visibility ??
                                album.published?.visibility
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Status</dt>
                        <dd>{{ album.draft ? "Draft" : "Published" }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Images</dt>
                        <dd>
                            {{
                                album.draft?.photos?.length ??
                                album.published?.photos?.length ??
                                0
                            }}
                        </dd>
                    </div>
                </dl>
                <div
                    class="mt-4 flex justify-end gap-1 border-t border-border/60 pt-3"
                >
                    <a
                        v-if="album.published"
                        :href="`/l/${lodge.slug}/galleries/${album.slug}`"
                        target="_blank"
                        class="icon-button"
                        title="View gallery"
                    >
                        <Eye class="size-4" />
                    </a>
                    <button
                        class="icon-button"
                        title="Edit gallery"
                        @click="editing = album"
                    >
                        <Pencil class="size-4" /></button
                    ><button
                        v-if="canPublish && album.draft"
                        class="icon-button"
                        title="Publish gallery"
                        @click="publish(album)"
                    >
                        <Rocket class="size-4" /></button
                    ><button
                        v-else-if="canPublish && album.published"
                        class="icon-button"
                        title="Unpublish gallery"
                        @click="unpublish(album)"
                    >
                        <Rocket class="size-4 rotate-180" /></button
                    ><button
                        v-if="!album.published"
                        class="icon-button text-destructive"
                        title="Delete gallery"
                        @click="remove(album)"
                    >
                        <Trash2 class="size-4" />
                    </button>
                </div>
            </article>
        </div>
        <div
            v-if="!albums.length"
            class="rounded-lg border border-dashed border-border/80 bg-card p-8 text-center text-sm text-muted-foreground"
        >
            No galleries match your search.
        </div>
    </main>
    <Dialog :open="creating" @update:open="creating = $event"
        ><DialogScrollContent class="max-w-xl"
            ><DialogHeader><DialogTitle>New gallery</DialogTitle></DialogHeader>
            <form class="grid gap-4" @submit.prevent="create">
                <label class="field-label"
                    >Title<input
                        v-model="form.title"
                        required
                        class="field-input" /></label
                ><label class="field-label"
                    >Slug<input
                        v-model="form.slug"
                        @input="form.slug = normalizeSlug(form.slug)"
                        required
                        class="field-input" /></label
                ><label class="field-label"
                    >Description<textarea
                        v-model="form.description"
                        class="field-input min-h-24"
                    /></label
                ><label class="field-label"
                    >Visibility<select
                        v-model="form.visibility"
                        class="field-input"
                    >
                        <option value="public">Public</option>
                        <option value="masons">All Masons</option>
                        <option value="lodge">Lodge members</option>
                    </select></label
                >
                <div class="flex justify-end">
                    <button class="primary-button">Create gallery</button>
                </div>
            </form></DialogScrollContent
        ></Dialog
    >
    <Dialog :open="!!editing" @update:open="!$event && (editing = null)"
        ><DialogScrollContent class="max-w-5xl"
            ><GalleryEditor
                v-if="editing"
                embedded
                :lodge="lodge"
                :album="editing"
                :draft="editing.draft ?? editing.published"
                :media="media"
                :can-publish="canPublish && !!editing.draft"
                @saved="editing = null"
                @open-media="mediaOpen = true" /></DialogScrollContent
    ></Dialog>
    <MediaLibraryModal
        :open="mediaOpen"
        :lodge="lodge"
        :media="media"
        @update:open="mediaOpen = $event"
    />
</template>
