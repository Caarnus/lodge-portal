<script setup lang="ts">
import RichTextField from "./RichTextField.vue";
defineProps<{ type: string; media: any[]; galleries: any[] }>();
const config = defineModel<Record<string, any>>({ required: true });
const placeholders = [
    "meeting_information",
    "contact_information",
    "officers_placeholder",
    "past_masters_placeholder",
    "events_placeholder",
    "newsletter_placeholder",
    "directory_placeholder",
    "gallery_placeholder",
];
const addLink = () => (config.value.links ??= []).push({ label: "", url: "" });
const galleryAlbumIds = () => (config.value.gallery_album_ids ??= []);
const gallerySelected = (id: number) => galleryAlbumIds().includes(id);
const toggleGallery = (id: number, selected: boolean) => {
    const ids = galleryAlbumIds();
    config.value.gallery_album_ids = selected
        ? [...new Set([...ids, id])]
        : ids.filter((item: number) => item !== id);
};
</script>

<template>
    <div class="grid gap-3">
        <template v-if="type === 'rich_text' || type === 'custom_html'">
            <RichTextField v-model="config.html" />
        </template>
        <template v-else-if="type === 'hero'">
            <label class="field-label"
                >Heading<input
                    v-model="config.heading"
                    required
                    class="field-input"
            /></label>
            <label class="field-label"
                >Body<textarea
                    v-model="config.body"
                    class="field-input min-h-24"
                ></textarea>
            </label>
            <label class="field-label"
                >Background image<select
                    v-model.number="config.media_id"
                    class="field-input"
                >
                    <option :value="null">None</option>
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
        </template>
        <template v-else-if="type === 'image' || type === 'image_text'">
            <label class="field-label"
                >Image<select
                    v-model.number="config.media_id"
                    required
                    class="field-input"
                >
                    <option disabled :value="null">Select image</option>
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
            <label v-if="type === 'image'" class="field-label"
                >Caption<input v-model="config.caption" class="field-input"
            /></label>
            <template v-else>
                <label class="field-label"
                    >Heading<input
                        v-model="config.heading"
                        required
                        class="field-input"
                /></label>
                <label class="field-label"
                    >Body<textarea
                        v-model="config.body"
                        class="field-input min-h-24"
                    ></textarea>
                </label>
                <label class="field-label"
                    >Image side<select
                        v-model="config.image_side"
                        class="field-input"
                    >
                        <option value="left">Left</option>
                        <option value="right">Right</option>
                    </select></label
                >
            </template>
        </template>
        <template v-else-if="type === 'call_to_action'">
            <label class="field-label"
                >Heading<input
                    v-model="config.heading"
                    required
                    class="field-input"
            /></label>
            <label class="field-label"
                >Body<textarea
                    v-model="config.body"
                    class="field-input min-h-20"
                ></textarea>
            </label>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="field-label"
                    >Button label<input
                        v-model="config.label"
                        required
                        class="field-input" /></label
                ><label class="field-label"
                    >Button link<input
                        v-model="config.url"
                        required
                        class="field-input"
                        placeholder="/contact or https://…"
                /></label>
            </div>
        </template>
        <template v-else-if="type === 'link_list'">
            <label class="field-label"
                >Heading<input v-model="config.heading" class="field-input"
            /></label>
            <div
                v-for="(link, index) in config.links"
                :key="index"
                class="grid gap-2 sm:grid-cols-[1fr_2fr_auto]"
            >
                <input
                    v-model="link.label"
                    aria-label="Link label"
                    required
                    class="field-input"
                    placeholder="Label"
                /><input
                    v-model="link.url"
                    aria-label="Link destination"
                    required
                    class="field-input"
                    placeholder="/page or https://…"
                /><button
                    type="button"
                    class="secondary-button"
                    @click="config.links.splice(index, 1)"
                >
                    Remove
                </button>
            </div>
            <button
                type="button"
                class="secondary-button w-fit text-sm"
                @click="addLink"
            >
                Add link
            </button>
        </template>
        <template v-else-if="type === 'events_placeholder'">
            <label class="field-label"
                >Heading<input v-model="config.heading" class="field-input"
            /></label>
            <label class="field-label"
                >Empty-state message<textarea
                    v-model="config.body"
                    class="field-input min-h-20"
                ></textarea>
            </label>
            <label class="field-label"
                >Maximum items<input
                    v-model.number="config.maximum_items"
                    type="number"
                    min="1"
                    max="20"
                    class="field-input"
            /></label>
            <label class="flex items-center gap-2 text-sm"
                ><input v-model="config.show_all_link" type="checkbox" /> Show
                complete event-list link</label
            >
        </template>
        <template v-else-if="type === 'contact_information'">
            <label class="field-label"
                >Heading<input v-model="config.heading" class="field-input"
            /></label>
            <label class="field-label"
                >Message<textarea
                    v-model="config.body"
                    class="field-input min-h-20"
                ></textarea>
            </label>
            <label class="field-toggle"
                ><input v-model="config.show_contact_form" type="checkbox" />
                Enable contact form</label
            >
        </template>
        <template v-else-if="type === 'gallery_placeholder'">
            <label class="field-label"
                >Heading<input v-model="config.heading" class="field-input"
            /></label>
            <label class="field-label"
                >Message<textarea
                    v-model="config.body"
                    class="field-input min-h-20"
                ></textarea>
            </label>
            <fieldset
                class="rounded-lg border border-border/80 bg-muted/30 p-4"
            >
                <legend class="px-1 text-sm font-medium">Albums to show</legend>
                <p class="mb-3 text-sm text-muted-foreground">
                    Select the published albums displayed in this section.
                </p>
                <div v-if="galleries.length" class="grid gap-2 sm:grid-cols-2">
                    <label
                        v-for="album in galleries"
                        :key="album.id"
                        class="field-toggle"
                    >
                        <input
                            type="checkbox"
                            :checked="gallerySelected(album.id)"
                            @change="
                                toggleGallery(
                                    album.id,
                                    ($event.target as HTMLInputElement).checked,
                                )
                            "
                        />
                        {{ album.title }}
                    </label>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    Publish a gallery before adding it to this section.
                </p>
            </fieldset>
        </template>
        <template v-else-if="placeholders.includes(type)">
            <label class="field-label"
                >Heading<input v-model="config.heading" class="field-input"
            /></label>
            <label class="field-label"
                >Message<textarea
                    v-model="config.body"
                    class="field-input min-h-20"
                ></textarea>
            </label>
        </template>
    </div>
</template>
