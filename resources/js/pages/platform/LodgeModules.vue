<script lang="ts" setup>
import {Head, Link, router} from "@inertiajs/vue3";
import AppLayout from "@/layouts/AppLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import {Badge} from "@/components/ui/badge";
import {Button} from "@/components/ui/button";
import {Card, CardContent, CardDescription, CardHeader, CardTitle} from "@/components/ui/card";
import {Checkbox} from "@/components/ui/checkbox";
import {Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle} from "@/components/ui/dialog";
import {computed, ref} from "vue";

defineOptions({layout: AppLayout});
type Module = { id: number; key: string; name: string; description?: string | null; is_available: boolean; is_enabled: boolean; is_effective: boolean };
const props = defineProps<{ lodge: { id: number; name: string }; modules: Module[] }>();
const pending = ref<{ module: Module; isAvailable: boolean } | null>(null);
const dialogOpen = computed({get: () => pending.value !== null, set: (value: boolean) => { if (!value) pending.value = null; }});

function requestChange(module: Module, isAvailable: boolean) { pending.value = {module, isAvailable}; }
function confirmChange() {
    if (!pending.value) return;
    router.put(`/platform/lodges/${props.lodge.id}/modules/${pending.value.module.id}`, {is_available: pending.value.isAvailable}, {onSuccess: () => pending.value = null});
}
</script>

<template>
    <Head :title="`Optional modules — ${lodge.name}`" />
    <main class="mx-auto w-full max-w-4xl space-y-6 p-4 sm:p-6 lg:p-8">
        <PageHeader eyebrow="Platform administration" :title="`Optional modules for ${lodge.name}`" description="Availability is the platform decision. A lodge preference is retained when availability is revoked.">
            <template #actions><Button as-child variant="outline"><Link :href="`/platform/lodges/${lodge.id}/edit`">Back to lodge</Link></Button></template>
        </PageHeader>
        <Card v-if="!modules.length"><CardContent class="p-6 text-sm text-muted-foreground">No optional modules are defined for this release.</CardContent></Card>
        <div v-else class="grid gap-4">
            <Card v-for="module in modules" :key="module.id">
                <CardHeader class="gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div><CardTitle>{{ module.name }}</CardTitle><CardDescription>{{ module.description || 'Optional lodge capability.' }}</CardDescription></div>
                    <div class="flex flex-wrap gap-2"><Badge :variant="module.is_available ? 'default' : 'muted'">{{ module.is_available ? 'Available' : 'Unavailable' }}</Badge><Badge :variant="module.is_enabled ? 'secondary' : 'muted'">Lodge preference: {{ module.is_enabled ? 'Enabled' : 'Disabled' }}</Badge><Badge :variant="module.is_effective ? 'default' : 'muted'">Effective: {{ module.is_effective ? 'Enabled' : 'Disabled' }}</Badge></div>
                </CardHeader>
                <CardContent><label class="flex items-center gap-3 rounded-md border border-border/80 bg-muted/30 p-3 text-sm"><Checkbox :checked="module.is_available" :aria-label="`Make ${module.name} available`" @update:checked="requestChange(module, Boolean($event))" /><span><span class="block font-medium">Make available to this lodge</span><span class="block text-muted-foreground">Revoking availability overrides the lodge preference without erasing it.</span></span></label></CardContent>
            </Card>
        </div>
    </main>
    <Dialog v-model:open="dialogOpen"><DialogContent><DialogHeader><DialogTitle>Confirm availability change</DialogTitle><DialogDescription>{{ pending?.isAvailable ? `Allow ${pending?.module.name} for ${lodge.name}?` : `Revoke ${pending?.module.name} availability for ${lodge.name}?` }} The lodge preference and module data are preserved.</DialogDescription></DialogHeader><DialogFooter><Button variant="outline" @click="pending = null">Cancel</Button><Button :variant="pending?.isAvailable ? 'default' : 'destructive'" @click="confirmChange">Confirm</Button></DialogFooter></DialogContent></Dialog>
</template>
