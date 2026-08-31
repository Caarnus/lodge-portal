<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from "@/components/ui/collapsible";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Head, router } from "@inertiajs/vue3";
import { ChevronDown } from "lucide-vue-next";
import { ref } from "vue";

const props = defineProps<{
    categories: any[];
    proficiencies: Record<string, any>;
    settings: any;
    availability: any[];
    progress: any;
}>();
const visibilityScope = ref(props.settings.visibility_scope);
const note = ref(props.settings.public_availability_note ?? "");
const days = [
    "Monday",
    "Tuesday",
    "Wednesday",
    "Thursday",
    "Friday",
    "Saturday",
    "Sunday",
];
const dayparts = ["morning", "afternoon", "evening"];
const selectedWindows = ref(
    new Set(
        props.availability.map(
            (window: any) => `${window.day_of_week}:${window.daypart}`,
        ),
    ),
);
const localProficiencies = ref<Record<string, any>>({ ...props.proficiencies });
const saving = ref<Record<string, boolean>>({});
const errors = ref<Record<string, string>>({});
const versions = new Map<number, number>();
const queues = new Map<number, Promise<void>>();
const saveSettings = () =>
    router.put("/ritual/settings", {
        visibility_scope: visibilityScope.value,
        public_availability_note: note.value,
    });
const saved = (part: any) =>
    localProficiencies.value[String(part.id)] ?? {
        status: "not_known",
        interested_in_learning: false,
        willing_to_assist: false,
        performed_for_credit: false,
        first_marked_proficient_on: null,
        notes: "",
    };
const payload = (state: any) => ({
    status: state.status,
    interested_in_learning: Boolean(state.interested_in_learning),
    willing_to_assist: Boolean(state.willing_to_assist),
    performed_for_credit: Boolean(state.performed_for_credit),
    confirm_performed_for_credit: Boolean(state.performed_for_credit),
    first_marked_proficient_on: state.first_marked_proficient_on || null,
    notes: state.notes || null,
});
const failureMessage = async (response: Response) => {
    const body = await response.json().catch(() => null);
    return body?.errors
        ? Object.values(body.errors).flat().join(" ")
        : "Could not save this part. Please try again.";
};
const update = (part: any, values: any) => {
    const key = String(part.id);
    const previous = { ...saved(part) };
    const next = { ...previous, ...values };
    const version = (versions.get(part.id) ?? 0) + 1;
    versions.set(part.id, version);
    localProficiencies.value[key] = next;
    saving.value[key] = true;
    delete errors.value[key];
    const request = async () => {
        const response = await fetch(`/ritual/parts/${part.id}`, {
            method: "PUT",
            credentials: "same-origin",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-Requested-With": "XMLHttpRequest",
                "X-CSRF-TOKEN":
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute("content") ?? "",
            },
            body: JSON.stringify(payload(next)),
        });
        if (!response.ok) throw new Error(await failureMessage(response));
    };
    const queued = (queues.get(part.id) ?? Promise.resolve())
        .catch(() => undefined)
        .then(request);
    queues.set(
        part.id,
        queued.then(
            () => undefined,
            () => undefined,
        ),
    );
    queued
        .then(() => {
            if (versions.get(part.id) === version) saving.value[key] = false;
        })
        .catch((error: Error) => {
            if (versions.get(part.id) === version) {
                localProficiencies.value[key] = previous;
                saving.value[key] = false;
                errors.value[key] = error.message;
            }
        });
};
const completion = (part: any) =>
    !saved(part).performed_for_credit
        ? "not_completed"
        : saved(part).willing_to_assist
          ? "completed_and_willing"
          : "completed";
const completionLabel = (part: any) =>
    ({
        not_completed: "Not completed",
        completed: "Completed",
        completed_and_willing: "Completed + willing to assist",
    })[completion(part)];
const completionButtonClass = (part: any) =>
    ({
        not_completed:
            "border-muted-foreground/25 bg-muted/40 hover:bg-muted/70",
        completed: "border-sky-500/40 bg-sky-500/10 hover:bg-sky-500/15",
        completed_and_willing:
            "border-emerald-600/40 bg-emerald-500/10 hover:bg-emerald-500/15",
    })[completion(part)];
const statusSelectClass = (part: any) => {
    const status = saved(part).status;
    if (status === "learning") return "border-sky-500/40 bg-sky-500/10";
    if (status === "proficient")
        return "border-emerald-600/40 bg-emerald-500/10";
    return "border-muted-foreground/25 bg-muted/40";
};
const advanceCompletion = (part: any) => {
    const next = {
        not_completed: "completed",
        completed: "completed_and_willing",
        completed_and_willing: "not_completed",
    }[completion(part)];
    update(
        part,
        next === "not_completed"
            ? { performed_for_credit: false, willing_to_assist: false }
            : {
                  status: "proficient",
                  performed_for_credit: true,
                  confirm_performed_for_credit: true,
                  willing_to_assist: next === "completed_and_willing",
              },
    );
};
const toggleWindow = (day: number, daypart: string) => {
    const key = `${day}:${daypart}`;
    if (selectedWindows.value.has(key)) selectedWindows.value.delete(key);
    else selectedWindows.value.add(key);
    selectedWindows.value = new Set(selectedWindows.value);
};
const saveAvailability = () =>
    router.put("/ritual/availability", {
        windows: [...selectedWindows.value].map((key) => {
            const [day_of_week, daypart] = key.split(":");
            return { day_of_week: Number(day_of_week), daypart };
        }),
    });
</script>

<template>
    <Head title="Ritual" />
    <AppLayout :breadcrumbs="[{ title: 'Ritual', href: '/ritual' }]">
        <main class="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6">
            <p
                v-if="Object.keys(errors).length"
                class="rounded border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive"
                role="alert"
            >
                {{ Object.values(errors).join(" ") }}
            </p>
            <PageHeader
                title="Ritual progress"
                description="Track your self-reported ritual knowledge, open-lodge credit, and general availability."
            >
                <template #actions
                    ><Badge
                        >{{ progress.current_total }} current points</Badge
                    ></template
                >
            </PageHeader>
            <Card
                ><CardHeader
                    ><CardTitle>Visibility and availability</CardTitle
                    ><CardDescription
                        >Choose who can discover your ritual
                        availability.</CardDescription
                    ></CardHeader
                ><CardContent>
                    <select v-model="visibilityScope" class="field-input">
                        <option value="hidden">Hidden</option>
                        <option value="own_lodge">Own lodges</option>
                        <option value="participating_lodges">
                            WorkingTools lodges
                        </option></select
                    ><textarea
                        v-model="note"
                        class="field-input mt-3 min-h-24"
                        maxlength="500"
                        placeholder="General availability note (visible only in ritual search)"
                    /><Button class="mt-3" @click="saveSettings">
                        Save visibility
                    </Button>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Availability is informational only and creates no
                        commitment, booking, or assignment.
                    </p>
                </CardContent></Card
            >
            <Card
                ><CardHeader
                    ><CardTitle>General availability</CardTitle
                    ><CardDescription>
                        Choose broad weekday/daypart windows. This is not a
                        reservation or commitment.
                    </CardDescription></CardHeader
                ><CardContent
                    ><div class="space-y-3 md:hidden">
                        <div
                            v-for="(day, index) in days"
                            :key="day"
                            class="rounded-md border border-border/60 bg-muted/30 p-3"
                        >
                            <p class="font-medium">{{ day }}</p>
                            <div class="mt-3 grid grid-cols-3 gap-2">
                                <label
                                    v-for="daypart in dayparts"
                                    :key="daypart"
                                    class="flex flex-col items-center gap-2 rounded-md border border-border/50 bg-background px-2 py-3 text-center text-xs capitalize"
                                >
                                    <Checkbox
                                        :checked="
                                            selectedWindows.has(
                                                `${index + 1}:${daypart}`,
                                            )
                                        "
                                        :aria-label="`${day} ${daypart}`"
                                        @update:checked="
                                            toggleWindow(index + 1, daypart)
                                        "
                                    />
                                    {{ daypart }}
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="hidden overflow-x-auto md:block">
                        <table class="w-full min-w-[480px] text-sm">
                            <thead>
                                <tr>
                                    <th class="p-2 text-left">Day</th>
                                    <th
                                        v-for="daypart in dayparts"
                                        :key="daypart"
                                        class="p-2 capitalize"
                                    >
                                        {{ daypart }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(day, index) in days" :key="day">
                                    <th class="p-2 text-left">{{ day }}</th>
                                    <td
                                        v-for="daypart in dayparts"
                                        :key="daypart"
                                        class="p-2 text-center"
                                    >
                                        <Checkbox
                                            :checked="
                                                selectedWindows.has(
                                                    `${index + 1}:${daypart}`,
                                                )
                                            "
                                            :aria-label="`${day} ${daypart}`"
                                            @update:checked="
                                                toggleWindow(index + 1, daypart)
                                            "
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <Button class="mt-4" @click="saveAvailability">
                        Save availability
                    </Button>
                </CardContent></Card
            >

            <Card
                ><CardHeader
                    ><CardTitle>Completed in an open lodge</CardTitle
                    ><CardDescription>
                        Click a completion button to cycle: not completed,
                        completed from memory in an open lodge, then completed
                        and willing to assist. The last state does not accept an
                        assignment.
                    </CardDescription></CardHeader
                ><CardContent>
                    <Collapsible
                        v-for="(category, categoryIndex) in categories"
                        :key="category.id"
                        v-slot="{ open }"
                        :default-open="categoryIndex === 0"
                        class="mt-3 overflow-hidden rounded-lg border border-border/80 bg-muted/30 first:mt-0"
                    >
                        <CollapsibleTrigger as-child>
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-3 p-4 text-left"
                            >
                                <span>
                                    <span class="block font-semibold">{{
                                        category.name
                                    }}</span>
                                    <span
                                        class="block text-sm text-muted-foreground"
                                        >{{ category.parts.length }} ritual
                                        parts</span
                                    >
                                </span>
                                <ChevronDown
                                    class="size-4 shrink-0 transition-transform"
                                    :class="open && 'rotate-180'"
                                />
                            </button>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <div
                                class="space-y-3 border-t border-border/70 p-3"
                            >
                                <div
                                    v-for="part in category.parts"
                                    :key="part.id"
                                    class="grid gap-3 rounded-md border border-border/50 bg-background p-3 md:grid-cols-[1fr_auto]"
                                >
                                    <div>
                                        <strong>{{ part.name }}</strong>
                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            {{
                                                part.point_value
                                                    ? `${part.point_value} points`
                                                    : "Does not count toward program points"
                                            }}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        class="flex min-h-16 w-full flex-col items-start justify-center rounded-md border px-3 py-2 text-left text-sm transition-colors md:w-64"
                                        :class="completionButtonClass(part)"
                                        :aria-label="`${part.name}: ${completionLabel(part)}. Click to change.`"
                                        @click="advanceCompletion(part)"
                                    >
                                        <span
                                            class="text-xs text-muted-foreground"
                                            >Open-lodge completion</span
                                        ><span class="font-medium">{{
                                            completionLabel(part)
                                        }}</span>
                                    </button>
                                </div>
                            </div>
                        </CollapsibleContent>
                    </Collapsible>
                </CardContent></Card
            >

            <Card
                ><CardHeader
                    ><CardTitle>Study and proficiency</CardTitle
                    ><CardDescription>
                        Set your current self-reported knowledge and learning
                        interest.
                    </CardDescription></CardHeader
                ><CardContent>
                    <Collapsible
                        v-for="(category, categoryIndex) in categories"
                        :key="category.id"
                        v-slot="{ open }"
                        :default-open="categoryIndex === 0"
                        class="mt-3 overflow-hidden rounded-lg border border-border/80 bg-muted/30 first:mt-0"
                    >
                        <CollapsibleTrigger as-child>
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-3 p-4 text-left"
                            >
                                <span>
                                    <span class="block font-semibold">{{
                                        category.name
                                    }}</span>
                                    <span
                                        class="block text-sm text-muted-foreground"
                                        >{{ category.parts.length }} ritual
                                        parts</span
                                    >
                                </span>
                                <ChevronDown
                                    class="size-4 shrink-0 transition-transform"
                                    :class="open && 'rotate-180'"
                                />
                            </button>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <div
                                class="space-y-3 border-t border-border/70 p-3"
                            >
                                <div
                                    v-for="part in category.parts"
                                    :key="part.id"
                                    class="grid gap-3 rounded-md border border-border/50 bg-background p-3"
                                >
                                    <strong>{{ part.name }}</strong>
                                    <div
                                        class="grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] xl:items-end"
                                    >
                                        <div class="block w-full">
                                            <Label>Status</Label>
                                            <select
                                                class="field-input mt-1"
                                                :class="statusSelectClass(part)"
                                                :value="saved(part).status"
                                                @change="
                                                    update(part, {
                                                        status: (
                                                            $event.target as HTMLSelectElement
                                                        ).value,
                                                    })
                                                "
                                            >
                                                <option value="not_known">
                                                    Not known
                                                </option>
                                                <option value="learning">
                                                    Learning
                                                </option>
                                                <option value="proficient">
                                                    Proficient
                                                </option>
                                            </select>
                                        </div>
                                        <div class="block w-full">
                                            <Label>First proficient</Label>
                                            <Input
                                                type="date"
                                                class="mt-1"
                                                :value="
                                                    saved(part)
                                                        .first_marked_proficient_on ??
                                                    ''
                                                "
                                                @change="
                                                    update(part, {
                                                        first_marked_proficient_on:
                                                            (
                                                                $event.target as HTMLInputElement
                                                            ).value || null,
                                                    })
                                                "
                                            />
                                        </div>
                                        <div
                                            class="block w-full md:col-span-2 xl:col-span-1"
                                        >
                                            <Label
                                                >Interested in learning</Label
                                            >
                                            <label class="checkbox-field mt-2"
                                                ><Checkbox
                                                    :checked="
                                                        saved(part)
                                                            .interested_in_learning
                                                    "
                                                    @update:checked="
                                                        update(part, {
                                                            interested_in_learning:
                                                                Boolean($event),
                                                        })
                                                    "
                                                />
                                                Yes</label
                                            >
                                        </div>
                                    </div>
                                    <textarea
                                        class="field-input min-h-20 text-sm"
                                        :value="saved(part).notes ?? ''"
                                        maxlength="2000"
                                        placeholder="Private notes"
                                        @change="
                                            update(part, {
                                                notes:
                                                    (
                                                        $event.target as HTMLTextAreaElement
                                                    ).value || null,
                                            })
                                        "
                                    />
                                </div>
                            </div>
                        </CollapsibleContent>
                    </Collapsible> </CardContent
            ></Card>
            <Collapsible
                v-if="progress.credited_retired_parts.length"
                v-slot="{ open }"
            >
                <Card class="overflow-hidden">
                    <CollapsibleTrigger as-child>
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-3 p-6 text-left"
                        >
                            <span>
                                <span class="block font-semibold"
                                    >Retired credited parts</span
                                >
                                <span
                                    class="block text-sm text-muted-foreground"
                                >
                                    {{ progress.credited_retired_parts.length }}
                                    historical credit
                                    {{
                                        progress.credited_retired_parts
                                            .length === 1
                                            ? "claim"
                                            : "claims"
                                    }}
                                </span>
                            </span>
                            <ChevronDown
                                class="size-4 shrink-0 transition-transform"
                                :class="open && 'rotate-180'"
                            />
                        </button>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <CardContent
                            class="border-t border-border/70 pt-6 text-sm text-muted-foreground"
                        >
                            <p>
                                These historical credit claims no longer count
                                toward the current total.
                            </p>
                            <ul class="mt-3 list-disc pl-5 text-foreground">
                                <li
                                    v-for="item in progress.credited_retired_parts"
                                    :key="item.id"
                                >
                                    {{ item.part.name }}
                                </li>
                            </ul>
                        </CardContent>
                    </CollapsibleContent>
                </Card>
            </Collapsible>
        </main>
    </AppLayout>
</template>
