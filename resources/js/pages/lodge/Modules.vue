<script lang="ts" setup>
import {Head, router} from "@inertiajs/vue3";
import AppLayout from "@/layouts/AppLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import WorkspaceTabs from "@/components/WorkspaceTabs.vue";
import {Badge} from "@/components/ui/badge";
import {Button} from "@/components/ui/button";
import {Card, CardContent, CardDescription, CardHeader, CardTitle} from "@/components/ui/card";
import {Checkbox} from "@/components/ui/checkbox";
import {Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle} from "@/components/ui/dialog";
import {computed, ref} from "vue";

defineOptions({layout: AppLayout});
type Module = { id: number; key: string; name: string; description?: string | null; is_available: boolean; is_enabled: boolean; is_effective: boolean };
const props = defineProps<{ lodge: { id: number; name: string }; modules: Module[] }>();
const pending = ref<{ module: Module; isEnabled: boolean } | null>(null);
const dialogOpen = computed({get: () => pending.value !== null, set: (value: boolean) => { if (!value) pending.value = null; }});
function requestChange(module: Module, isEnabled: boolean) { if (module.is_available) pending.value = {module, isEnabled}; }
function confirmChange() { if (!pending.value) return; router.put(`/lodges/${props.lodge.id}/modules/${pending.value.module.id}`, {is_enabled: pending.value.isEnabled}, {onSuccess: () => pending.value = null}); }
</script>

<template>
    <Head :title="`Optional modules — ${lodge.name}`" />
    <main class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">
        <PageHeader eyebrow="Lodge settings" title="Optional modules" description="Enable an available module for this lodge. Enablement does not grant module permissions or publish public content." />
        <WorkspaceTabs :lodge="lodge" active="modules" workspace="settings" />
        <Card v-if="!modules.length"><CardContent class="p-6 text-sm text-muted-foreground">No optional modules are defined for this release.</CardContent></Card>
        <div v-else class="grid gap-4">
            <Card v-for="module in modules" :key="module.id"><CardHeader class="gap-3 sm:flex-row sm:items-start sm:justify-between"><div><CardTitle>{{ module.name }}</CardTitle><CardDescription>{{ module.description || 'Optional lodge capability.' }}</CardDescription></div><div class="flex flex-wrap gap-2"><Badge :variant="module.is_available ? 'secondary' : 'muted'">{{ module.is_available ? 'Available' : 'Unavailable' }}</Badge><Badge :variant="module.is_effective ? 'default' : 'muted'">Effective: {{ module.is_effective ? 'Enabled' : 'Disabled' }}</Badge></div></CardHeader><CardContent><label class="flex items-center gap-3 rounded-md border border-border/80 bg-muted/30 p-3 text-sm"><Checkbox :checked="module.is_enabled" :disabled="!module.is_available" :aria-label="`Enable ${module.name}`" @update:checked="requestChange(module, Boolean($event))" /><span><span class="block font-medium">{{ module.is_available ? 'Enable this module' : 'This module is unavailable' }}</span><span class="block text-muted-foreground">{{ module.is_available ? 'Your choice is retained if platform availability changes.' : 'Ask a platform administrator to make it available.' }}</span></span></label></CardContent></Card>
        </div>
    </main>
    <Dialog v-model:open="dialogOpen"><DialogContent><DialogHeader><DialogTitle>Confirm module preference</DialogTitle><DialogDescription>{{ pending?.isEnabled ? `Enable ${pending?.module.name} for ${lodge.name}?` : `Disable ${pending?.module.name} for ${lodge.name}?` }} Module data is preserved.</DialogDescription></DialogHeader><DialogFooter><Button variant="outline" @click="pending = null">Cancel</Button><Button :variant="pending?.isEnabled ? 'default' : 'destructive'" @click="confirmChange">Confirm</Button></DialogFooter></DialogContent></Dialog>
</template>
