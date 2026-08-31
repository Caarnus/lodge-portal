<script setup lang="ts">
import {
    Dialog,
    DialogDescription,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from "@/components/ui/dialog";
import { router, useForm } from "@inertiajs/vue3";
import {
    Check,
    ImagePlus,
    Pencil,
    RotateCcw,
    Trash2,
    X,
} from "lucide-vue-next";
import { ref } from "vue";

const props = defineProps<{ lodge: any; media: any[]; open: boolean }>();
const emit = defineEmits<{ "update:open": [value: boolean] }>();

const upload = useForm<{ file: File | null; alt_text: string }>({
    file: null,
    alt_text: "",
});
const editingId = ref<number | null>(null);
const altText = ref("");
const error = ref("");

const close = () => emit("update:open", false);
const uploadMedia = () => {
    error.value = "";
    upload.post(`/lodges/${props.lodge.id}/website/media`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => upload.reset(),
        onError: (errors) => {
            error.value = errors.file ?? errors.alt_text ?? "Upload failed.";
        },
    });
};
const editAltText = (asset: any) => {
    editingId.value = asset.id;
    altText.value = asset.alt_text ?? "";
};
const saveAltText = (asset: any) => {
    error.value = "";
    router.put(
        `/lodges/${props.lodge.id}/website/media/${asset.id}`,
        { alt_text: altText.value },
        {
            preserveScroll: true,
            onSuccess: () => (editingId.value = null),
            onError: (errors) => {
                error.value =
                    errors.alt_text ?? "Alternative text could not be saved.";
            },
        },
    );
};
const retry = (asset: any) =>
    router.post(
        `/lodges/${props.lodge.id}/website/media/${asset.id}/retry`,
        {},
        { preserveScroll: true },
    );
const remove = (asset: any) => {
    error.value = "";
    router.delete(`/lodges/${props.lodge.id}/website/media/${asset.id}`, {
        preserveScroll: true,
        onError: (errors) => {
            error.value = errors.media ?? "Media could not be deleted.";
        },
    });
};
</script>

<template>
    <Dialog :open="open" @update:open="!$event && close()">
        <DialogScrollContent class="w-[calc(100vw-2rem)] max-w-5xl">
            <DialogHeader>
                <DialogTitle>Media library</DialogTitle>
                <DialogDescription>
                    Upload and maintain images used throughout this lodge’s
                    public content.
                </DialogDescription>
            </DialogHeader>
            <form
                class="grid gap-4 rounded-lg border border-border/80 bg-muted/25 p-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]"
                @submit.prevent="uploadMedia"
            >
                <label class="field-label">
                    Image file
                    <input
                        required
                        type="file"
                        accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif"
                        class="file-input"
                        @change="
                            upload.file =
                                ($event.target as HTMLInputElement)
                                    .files?.[0] ?? null
                        "
                    />
                </label>
                <label class="field-label">
                    Alternative text
                    <input
                        v-model="upload.alt_text"
                        required
                        class="field-input"
                    />
                </label>
                <button
                    class="primary-button self-end"
                    :disabled="upload.processing"
                >
                    <ImagePlus class="mr-1 size-4" /> Upload image
                </button>
            </form>
            <p
                v-if="error"
                class="mt-4 rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive"
                role="alert"
            >
                {{ error }}
            </p>
            <div
                v-if="media.length"
                class="mt-5 hidden overflow-hidden rounded-lg border border-border/80 bg-card md:block"
            >
                <table class="w-full table-fixed text-left text-sm">
                    <colgroup>
                        <col class="w-20" />
                        <col class="w-44" />
                        <col />
                        <col class="w-24" />
                        <col class="w-32" />
                    </colgroup>
                    <thead class="border-b bg-muted/40">
                        <tr>
                            <th class="p-3 font-medium text-muted-foreground">
                                Image
                            </th>
                            <th class="p-3 font-medium text-muted-foreground">
                                File
                            </th>
                            <th class="p-3 font-medium text-muted-foreground">
                                Alternative text
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
                            v-for="asset in media"
                            :key="asset.id"
                            class="border-b border-border/60 transition-colors last:border-0 hover:bg-muted/35"
                        >
                            <td class="p-2">
                                <img
                                    v-if="asset.url"
                                    :src="asset.url"
                                    :alt="asset.alt_text"
                                    class="size-14 rounded object-cover"
                                /><span
                                    v-else
                                    class="inline-flex size-14 items-center justify-center rounded bg-muted text-xs text-muted-foreground"
                                    >Pending</span
                                >
                            </td>
                            <td
                                class="truncate p-3"
                                :title="asset.original_name"
                            >
                                {{ asset.original_name }}
                            </td>
                            <td class="p-3">
                                <div
                                    v-if="editingId === asset.id"
                                    class="flex gap-2"
                                >
                                    <input
                                        v-model="altText"
                                        class="field-input"
                                    /><button
                                        type="button"
                                        class="icon-button"
                                        aria-label="Save alternative text"
                                        @click="saveAltText(asset)"
                                    >
                                        <Check class="size-4" /></button
                                    ><button
                                        type="button"
                                        class="icon-button"
                                        aria-label="Cancel"
                                        @click="editingId = null"
                                    >
                                        <X class="size-4" />
                                    </button>
                                </div>
                                <span
                                    v-else
                                    class="block truncate"
                                    :title="asset.alt_text"
                                    >{{ asset.alt_text }}</span
                                >
                            </td>
                            <td class="p-3 capitalize">
                                {{ asset.processing_status }}
                            </td>
                            <td class="p-3">
                                <div class="flex min-w-32 justify-end gap-1">
                                    <button
                                        v-if="editingId !== asset.id"
                                        type="button"
                                        class="icon-button"
                                        aria-label="Edit alternative text"
                                        @click="editAltText(asset)"
                                    >
                                        <Pencil class="size-4" /></button
                                    ><button
                                        v-if="
                                            asset.processing_status === 'failed'
                                        "
                                        type="button"
                                        class="icon-button"
                                        aria-label="Retry processing"
                                        @click="retry(asset)"
                                    >
                                        <RotateCcw class="size-4" /></button
                                    ><button
                                        type="button"
                                        class="icon-button border-destructive/50 text-destructive hover:bg-destructive/10"
                                        aria-label="Delete image"
                                        @click="remove(asset)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="media.length" class="mt-5 space-y-3 md:hidden">
                <article
                    v-for="asset in media"
                    :key="asset.id"
                    class="rounded-lg border border-border/80 bg-card p-4"
                >
                    <div class="flex gap-3">
                        <img
                            v-if="asset.url"
                            :src="asset.url"
                            :alt="asset.alt_text"
                            class="size-16 rounded object-cover"
                        /><span
                            v-else
                            class="inline-flex size-16 items-center justify-center rounded bg-muted text-xs text-muted-foreground"
                            >Pending</span
                        >
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">
                                {{ asset.original_name }}
                            </p>
                            <p
                                class="mt-1 text-xs capitalize text-muted-foreground"
                            >
                                {{ asset.processing_status }}
                            </p>
                        </div>
                    </div>
                    <label class="field-label mt-3">
                        Alternative text
                        <input
                            :value="asset.alt_text"
                            class="field-input"
                            @change="
                                altText = ($event.target as HTMLInputElement)
                                    .value;
                                saveAltText(asset);
                            "
                        />
                    </label>
                    <div
                        class="mt-4 flex justify-end gap-1 border-t border-border/60 pt-3"
                    >
                        <button
                            v-if="asset.processing_status === 'failed'"
                            type="button"
                            class="icon-button"
                            aria-label="Retry processing"
                            @click="retry(asset)"
                        >
                            <RotateCcw class="size-4" /></button
                        ><button
                            type="button"
                            class="icon-button border-destructive/50 text-destructive hover:bg-destructive/10"
                            aria-label="Delete image"
                            @click="remove(asset)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </article>
            </div>
            <p
                v-if="!media.length"
                class="mt-5 rounded-lg border border-dashed border-border/80 bg-muted/25 p-8 text-center text-sm text-muted-foreground"
            >
                No uploaded images yet.
            </p>
        </DialogScrollContent>
    </Dialog>
</template>
