<script setup lang="ts">
import RichTextField from './RichTextField.vue';
defineProps<{ type: string; media: any[] }>();
const config = defineModel<Record<string, any>>({ required: true });
const placeholders = ['meeting_information', 'contact_information', 'officers_placeholder', 'events_placeholder', 'newsletter_placeholder', 'gallery_placeholder'];
const addLink = () => (config.value.links ??= []).push({ label: '', url: '' });
</script>

<template>
    <div class="grid gap-3">
        <template v-if="type === 'rich_text' || type === 'custom_html'">
            <RichTextField v-model="config.html" />
        </template>
        <template v-else-if="type === 'hero'">
            <label class="field-label">Heading<input v-model="config.heading" required class="field-input" /></label>
            <label class="field-label">Body<textarea v-model="config.body" class="field-input min-h-24"></textarea></label>
            <label class="field-label">Background image<select v-model.number="config.media_id" class="field-input"><option :value="null">None</option><option v-for="asset in media.filter((item) => item.processing_status === 'ready')" :key="asset.id" :value="asset.id">{{ asset.original_name }}</option></select></label>
        </template>
        <template v-else-if="type === 'image' || type === 'image_text'">
            <label class="field-label">Image<select v-model.number="config.media_id" required class="field-input"><option disabled :value="null">Select image</option><option v-for="asset in media.filter((item) => item.processing_status === 'ready')" :key="asset.id" :value="asset.id">{{ asset.original_name }}</option></select></label>
            <label v-if="type === 'image'" class="field-label">Caption<input v-model="config.caption" class="field-input" /></label>
            <template v-else>
                <label class="field-label">Heading<input v-model="config.heading" required class="field-input" /></label>
                <label class="field-label">Body<textarea v-model="config.body" class="field-input min-h-24"></textarea></label>
                <label class="field-label">Image side<select v-model="config.image_side" class="field-input"><option value="left">Left</option><option value="right">Right</option></select></label>
            </template>
        </template>
        <template v-else-if="type === 'call_to_action'">
            <label class="field-label">Heading<input v-model="config.heading" required class="field-input" /></label>
            <label class="field-label">Body<textarea v-model="config.body" class="field-input min-h-20"></textarea></label>
            <div class="grid gap-3 sm:grid-cols-2"><label class="field-label">Button label<input v-model="config.label" required class="field-input" /></label><label class="field-label">Button link<input v-model="config.url" required class="field-input" placeholder="/contact or https://…" /></label></div>
        </template>
        <template v-else-if="type === 'link_list'">
            <label class="field-label">Heading<input v-model="config.heading" class="field-input" /></label>
            <div v-for="(link, index) in config.links" :key="index" class="grid gap-2 sm:grid-cols-[1fr_2fr_auto]"><input v-model="link.label" aria-label="Link label" required class="field-input" placeholder="Label" /><input v-model="link.url" aria-label="Link destination" required class="field-input" placeholder="/page or https://…" /><button type="button" class="rounded border px-3" @click="config.links.splice(index, 1)">Remove</button></div>
            <button type="button" class="w-fit rounded border px-3 py-2 text-sm" @click="addLink">Add link</button>
        </template>
        <template v-else-if="placeholders.includes(type)">
            <label class="field-label">Heading<input v-model="config.heading" class="field-input" /></label>
            <label class="field-label">Message<textarea v-model="config.body" class="field-input min-h-20"></textarea></label>
        </template>
    </div>
</template>
