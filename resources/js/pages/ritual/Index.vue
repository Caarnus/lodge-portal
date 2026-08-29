<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import { ref } from "vue";

const props = defineProps<{ categories: any[]; proficiencies: Record<string, any>; settings: any; availability: any[]; progress: any }>();
const visibilityScope = ref(props.settings.visibility_scope);
const note = ref(props.settings.public_availability_note ?? "");
const days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
const dayparts = ["morning", "afternoon", "evening"];
const selectedWindows = ref(new Set(props.availability.map((window: any) => `${window.day_of_week}:${window.daypart}`)));
const localProficiencies = ref<Record<string, any>>({ ...props.proficiencies });
const saving = ref<Record<string, boolean>>({});
const errors = ref<Record<string, string>>({});
const versions = new Map<number, number>();
const queues = new Map<number, Promise<void>>();
const saveSettings = () => router.put("/ritual/settings", { visibility_scope: visibilityScope.value, public_availability_note: note.value });
const saved = (part: any) => localProficiencies.value[String(part.id)] ?? { status: "not_known", interested_in_learning: false, willing_to_assist: false, performed_for_credit: false, first_marked_proficient_on: null, notes: "" };
const payload = (state: any) => ({ status: state.status, interested_in_learning: Boolean(state.interested_in_learning), willing_to_assist: Boolean(state.willing_to_assist), performed_for_credit: Boolean(state.performed_for_credit), confirm_performed_for_credit: Boolean(state.performed_for_credit), first_marked_proficient_on: state.first_marked_proficient_on || null, notes: state.notes || null });
const failureMessage = async (response: Response) => { const body = await response.json().catch(() => null); return body?.errors ? Object.values(body.errors).flat().join(" ") : "Could not save this part. Please try again."; };
const update = (part: any, values: any) => { const key = String(part.id); const previous = { ...saved(part) }; const next = { ...previous, ...values }; const version = (versions.get(part.id) ?? 0) + 1; versions.set(part.id, version); localProficiencies.value[key] = next; saving.value[key] = true; delete errors.value[key]; const request = async () => { const response = await fetch(`/ritual/parts/${part.id}`, { method: "PUT", credentials: "same-origin", headers: { Accept: "application/json", "Content-Type": "application/json", "X-Requested-With": "XMLHttpRequest", "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "" }, body: JSON.stringify(payload(next)) }); if (!response.ok) throw new Error(await failureMessage(response)); }; const queued = (queues.get(part.id) ?? Promise.resolve()).catch(() => undefined).then(request); queues.set(part.id, queued.then(() => undefined, () => undefined)); queued.then(() => { if (versions.get(part.id) === version) saving.value[key] = false; }).catch((error: Error) => { if (versions.get(part.id) === version) { localProficiencies.value[key] = previous; saving.value[key] = false; errors.value[key] = error.message; } }); };
const completion = (part: any) => !saved(part).performed_for_credit ? "not_completed" : saved(part).willing_to_assist ? "completed_and_willing" : "completed";
const advanceCompletion = (part: any) => { const next = { not_completed: "completed", completed: "completed_and_willing", completed_and_willing: "not_completed" }[completion(part)]; update(part, next === "not_completed" ? { performed_for_credit: false, willing_to_assist: false } : { status: "proficient", performed_for_credit: true, confirm_performed_for_credit: true, willing_to_assist: next === "completed_and_willing" }); };
const completionInput = (element: any, part: any) => { if (element instanceof HTMLInputElement) element.indeterminate = completion(part) === "completed"; };
const toggleWindow = (day: number, daypart: string) => { const key = `${day}:${daypart}`; if (selectedWindows.value.has(key)) selectedWindows.value.delete(key); else selectedWindows.value.add(key); selectedWindows.value = new Set(selectedWindows.value); };
const saveAvailability = () => router.put("/ritual/availability", { windows: [...selectedWindows.value].map((key) => { const [day_of_week, daypart] = key.split(":"); return { day_of_week: Number(day_of_week), daypart }; }) });
</script>

<template>
    <Head title="Ritual" />
    <AppLayout :breadcrumbs="[{ title: 'Ritual', href: '/ritual' }]">
        <main class="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6">
            <p v-if="Object.keys(errors).length" class="rounded border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive" role="alert">{{ Object.values(errors).join(" ") }}</p>
            <section class="rounded-lg border p-5"><h1 class="text-xl font-semibold">Ritual progress</h1><p class="mt-1 text-sm text-muted-foreground">Current points: {{ progress.current_total }}. Proficiency is self-reported; completed credit requires a performance from memory in an open lodge.</p></section>
            <section class="rounded-lg border p-5"><h2 class="font-semibold">Ritual visibility and availability</h2><select v-model="visibilityScope" class="mt-3 rounded border p-2"><option value="hidden">Hidden</option><option value="own_lodge">Own lodges</option><option value="participating_lodges">Participating lodges</option></select><textarea v-model="note" class="mt-3 w-full rounded border p-2" maxlength="500" placeholder="General availability note (visible only in ritual search)" /><button class="mt-2 rounded bg-primary px-3 py-2 text-primary-foreground" @click="saveSettings">Save visibility</button><p class="mt-2 text-xs text-muted-foreground">Availability is informational only and creates no commitment, booking, or assignment.</p></section>
            <section class="rounded-lg border p-5"><h2 class="font-semibold">General availability</h2><p class="mt-1 text-sm text-muted-foreground">Choose broad weekday/daypart windows. This is not a reservation or commitment.</p><div class="mt-3 overflow-x-auto"><table class="w-full text-sm"><thead><tr><th class="p-2 text-left">Day</th><th v-for="daypart in dayparts" :key="daypart" class="p-2 capitalize">{{ daypart }}</th></tr></thead><tbody><tr v-for="(day, index) in days" :key="day"><th class="p-2 text-left">{{ day }}</th><td v-for="daypart in dayparts" :key="daypart" class="p-2 text-center"><input type="checkbox" :checked="selectedWindows.has(`${index + 1}:${daypart}`)" :aria-label="`${day} ${daypart}`" @change="toggleWindow(index + 1, daypart)" /></td></tr></tbody></table></div><button class="mt-3 rounded bg-primary px-3 py-2 text-primary-foreground" @click="saveAvailability">Save availability</button></section>

            <section class="rounded-lg border p-5"><h2 class="font-semibold">Completed in an open lodge</h2><p class="mt-1 text-sm text-muted-foreground">Click each checkbox to cycle its three states: empty = not completed; dash = completed from memory in an open lodge; check = completed and willing to assist. The last state does not accept an assignment.</p><div v-for="category in categories" :key="category.id" class="mt-5"><h3 class="border-b pb-2 font-medium">{{ category.name }}</h3><div v-for="part in category.parts" :key="part.id" class="grid gap-3 border-b py-4 md:grid-cols-[1fr_auto]"><div><strong>{{ part.name }}</strong><p class="mt-1 text-sm text-muted-foreground">{{ part.point_value ? `${part.point_value} points` : 'Does not count toward program points' }}</p></div><label class="flex items-center gap-3 text-sm"><input :ref="(element) => completionInput(element, part)" type="checkbox" :checked="completion(part) === 'completed_and_willing'" :aria-checked="completion(part) === 'completed' ? 'mixed' : completion(part) === 'completed_and_willing'" :aria-label="`${part.name}: ${completion(part)}`" @click.prevent="advanceCompletion(part)" /><span>{{ completion(part) === 'not_completed' ? 'Not completed in open lodge' : completion(part) === 'completed' ? 'Completed in open lodge' : 'Completed + willing to assist' }}</span></label></div></div></section>

            <section class="rounded-lg border p-5"><h2 class="font-semibold">Study and proficiency</h2><p class="mt-1 text-sm text-muted-foreground">Set your current self-reported knowledge and learning interest.</p><div v-for="category in categories" :key="category.id" class="mt-5"><h3 class="border-b pb-2 font-medium">{{ category.name }}</h3><div v-for="part in category.parts" :key="part.id" class="grid gap-3 border-b py-4 md:grid-cols-[1fr_auto]"><div><strong>{{ part.name }}</strong></div><div class="grid gap-3 text-sm md:grid-cols-2"><label>Status <select class="ml-2 rounded border p-1" :value="saved(part).status" @change="update(part, { status: ($event.target as HTMLSelectElement).value })"><option value="not_known">Not known</option><option value="learning">Learning</option><option value="proficient">Proficient</option></select></label><label><input type="checkbox" :checked="saved(part).interested_in_learning" @change="update(part, { interested_in_learning: ($event.target as HTMLInputElement).checked })" /> Interested in learning</label><label>First proficient <input type="date" class="ml-2 rounded border p-1" :value="saved(part).first_marked_proficient_on ?? ''" @change="update(part, { first_marked_proficient_on: ($event.target as HTMLInputElement).value || null })" /></label></div><textarea class="md:col-span-2 w-full rounded border p-2 text-sm" :value="saved(part).notes ?? ''" maxlength="2000" placeholder="Private notes" @change="update(part, { notes: ($event.target as HTMLTextAreaElement).value || null })" /></div></div></section>
            <section v-if="progress.credited_retired_parts.length" class="rounded-lg border p-5"><h2 class="font-semibold">Retired credited parts</h2><p class="mt-1 text-sm text-muted-foreground">These historical credit claims no longer count toward the current total.</p><ul class="mt-3 list-disc pl-5"><li v-for="item in progress.credited_retired_parts" :key="item.id">{{ item.part.name }}</li></ul></section>
        </main>
    </AppLayout>
</template>

<style scoped>
@media (min-width: 768px) {
    main > section:nth-of-type(4),
    main > section:nth-of-type(5) {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        column-gap: 1.5rem;
    }

    main > section:nth-of-type(4) > h2,
    main > section:nth-of-type(4) > p,
    main > section:nth-of-type(5) > h2,
    main > section:nth-of-type(5) > p {
        grid-column: 1 / -1;
    }

    main > section:nth-of-type(4) > div,
    main > section:nth-of-type(5) > div {
        display: grid;
        grid-column: 1 / -1;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        column-gap: 1.5rem;
    }

    main > section:nth-of-type(4) > div > h3,
    main > section:nth-of-type(5) > div > h3 {
        grid-column: 1 / -1;
    }
}
</style>
