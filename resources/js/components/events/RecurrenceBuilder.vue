<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { computed, ref, watch } from "vue";

type Frequency = "none" | "daily" | "weekly" | "monthly" | "yearly";
type EndMode = "never" | "count" | "until";
type MonthlyMode = "day" | "weekday";

const props = defineProps<{ modelValue: string; startsAt: string }>();
const emit = defineEmits<{ "update:modelValue": [value: string] }>();
const open = ref(false);
const frequency = ref<Frequency>("none");
const interval = ref(1);
const weekdays = ref<string[]>([]);
const monthlyMode = ref<MonthlyMode>("day");
const monthDay = ref(1);
const ordinal = ref("1");
const ordinalWeekday = ref("MO");
const endMode = ref<EndMode>("never");
const count = ref(12);
const until = ref("");
const days = [
    { key: "MO", label: "Mon" },
    { key: "TU", label: "Tue" },
    { key: "WE", label: "Wed" },
    { key: "TH", label: "Thu" },
    { key: "FR", label: "Fri" },
    { key: "SA", label: "Sat" },
    { key: "SU", label: "Sun" },
];

const startDate = computed(() =>
    props.startsAt ? new Date(props.startsAt) : new Date(),
);
const summary = computed(() => {
    if (frequency.value === "none") return "Does not repeat";
    const singular =
        { daily: "day", weekly: "week", monthly: "month", yearly: "year" }[
            frequency.value
        ] ?? frequency.value;
    const every =
        interval.value === 1
            ? `Every ${singular}`
            : `Every ${interval.value} ${frequency.value}`;
    const detail =
        frequency.value === "weekly" && weekdays.value.length
            ? ` on ${weekdays.value.map((day) => days.find((item) => item.key === day)?.label).join(", ")}`
            : frequency.value === "monthly" && monthlyMode.value === "day"
              ? ` on day ${monthDay.value}`
              : frequency.value === "monthly"
                ? ` on the ${ordinalLabel(ordinal.value)} ${days.find((day) => day.key === ordinalWeekday.value)?.label}`
                : "";
    const ending =
        endMode.value === "count"
            ? `, ${count.value} times`
            : endMode.value === "until" && until.value
              ? `, through ${new Date(`${until.value}T00:00:00`).toLocaleDateString()}`
              : "";
    return `${every}${detail}${ending}`;
});

const parse = () => {
    frequency.value = "none";
    interval.value = 1;
    weekdays.value = [];
    monthlyMode.value = "day";
    monthDay.value = startDate.value.getDate();
    ordinal.value = "1";
    ordinalWeekday.value = "MO";
    endMode.value = "never";
    count.value = 12;
    until.value = "";
    const values = Object.fromEntries(
        props.modelValue
            .replace(/^RRULE:/, "")
            .split(";")
            .filter(Boolean)
            .map((part) => part.split("=")),
    );
    if (!values.FREQ) return;
    frequency.value = values.FREQ.toLowerCase() as Frequency;
    interval.value = Number(values.INTERVAL ?? 1);
    if (values.BYDAY) {
        const match = values.BYDAY.match(/^(-?\d)(MO|TU|WE|TH|FR|SA|SU)$/);
        if (frequency.value === "monthly" && match) {
            monthlyMode.value = "weekday";
            ordinal.value = match[1];
            ordinalWeekday.value = match[2];
        } else weekdays.value = values.BYDAY.split(",");
    }
    if (values.BYMONTHDAY) monthDay.value = Number(values.BYMONTHDAY);
    if (values.COUNT) {
        endMode.value = "count";
        count.value = Number(values.COUNT);
    }
    if (values.UNTIL) {
        endMode.value = "until";
        until.value = values.UNTIL.slice(0, 8).replace(
            /(\d{4})(\d{2})(\d{2})/,
            "$1-$2-$3",
        );
    }
};
const apply = () => {
    emit("update:modelValue", buildRule());
    open.value = false;
};
const buildRule = () => {
    if (frequency.value === "none") return "";
    const parts = [`FREQ=${frequency.value.toUpperCase()}`];
    if (interval.value > 1) parts.push(`INTERVAL=${interval.value}`);
    if (frequency.value === "weekly" && weekdays.value.length)
        parts.push(`BYDAY=${weekdays.value.join(",")}`);
    if (frequency.value === "monthly")
        parts.push(
            monthlyMode.value === "day"
                ? `BYMONTHDAY=${monthDay.value}`
                : `BYDAY=${ordinal.value}${ordinalWeekday.value}`,
        );
    if (endMode.value === "count") parts.push(`COUNT=${count.value}`);
    if (endMode.value === "until" && until.value)
        parts.push(`UNTIL=${until.value.replaceAll("-", "")}T235959Z`);
    return parts.join(";");
};
const ordinalLabel = (value: string) =>
    ({
        "1": "first",
        "2": "second",
        "3": "third",
        "4": "fourth",
        "-1": "last",
    })[value] ?? value;
watch(() => props.modelValue, parse, { immediate: true });
watch(open, (value) => {
    if (value) parse();
});
watch(frequency, (value) => {
    if (value === "weekly" && weekdays.value.length === 0) {
        weekdays.value = [days[(startDate.value.getDay() + 6) % 7].key];
    }
});
</script>

<template>
    <div class="space-y-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="text-sm font-medium">Repeats</span
            ><button
                type="button"
                class="cursor-pointer rounded-md border px-3 py-2 text-sm"
                @click="open = true"
            >
                {{ modelValue ? "Edit recurrence" : "Set recurrence" }}
            </button>
        </div>
        <p class="text-sm text-muted-foreground">
            {{ modelValue ? summary : "Does not repeat" }}
        </p>
    </div>
    <Dialog :open="open" @update:open="open = $event"
        ><DialogContent
            class="max-h-[calc(100vh-3rem)] max-w-xl overflow-y-auto"
            ><DialogHeader
                ><DialogTitle>Build a recurring schedule</DialogTitle
                ><DialogDescription
                    >Choose how this event repeats. The schedule follows the
                    event’s selected time zone.</DialogDescription
                ></DialogHeader
            >
            <div class="space-y-5 py-3">
                <label class="block text-sm font-medium"
                    >Repeat<select
                        v-model="frequency"
                        class="mt-1 w-full rounded-md border bg-background p-2"
                    >
                        <option value="none">Does not repeat</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                    </select></label
                ><label
                    v-if="frequency !== 'none'"
                    class="block text-sm font-medium"
                    >Every<input
                        v-model.number="interval"
                        type="number"
                        min="1"
                        max="99"
                        class="mt-1 w-28 rounded-md border bg-background p-2"
                    />
                    <span class="font-normal">{{ frequency }}</span></label
                >
                <div v-if="frequency === 'weekly'">
                    <p class="text-sm font-medium">On these days</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <label
                            v-for="day in days"
                            :key="day.key"
                            class="cursor-pointer rounded-md border px-3 py-2 text-sm"
                            :class="
                                weekdays.includes(day.key)
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : ''
                            "
                            ><input
                                v-model="weekdays"
                                :value="day.key"
                                type="checkbox"
                                class="sr-only"
                            />{{ day.label }}</label
                        >
                    </div>
                </div>
                <div v-if="frequency === 'monthly'" class="space-y-3">
                    <label class="flex items-center gap-2 text-sm"
                        ><input
                            v-model="monthlyMode"
                            value="day"
                            type="radio"
                        />
                        Day of month</label
                    ><input
                        v-if="monthlyMode === 'day'"
                        v-model.number="monthDay"
                        type="number"
                        min="1"
                        max="31"
                        class="w-28 rounded-md border bg-background p-2"
                    /><label class="flex items-center gap-2 text-sm"
                        ><input
                            v-model="monthlyMode"
                            value="weekday"
                            type="radio"
                        />
                        Ordinal weekday</label
                    >
                    <div v-if="monthlyMode === 'weekday'" class="flex gap-2">
                        <select
                            v-model="ordinal"
                            class="rounded-md border bg-background p-2"
                        >
                            <option value="1">First</option>
                            <option value="2">Second</option>
                            <option value="3">Third</option>
                            <option value="4">Fourth</option>
                            <option value="-1">Last</option></select
                        ><select
                            v-model="ordinalWeekday"
                            class="rounded-md border bg-background p-2"
                        >
                            <option
                                v-for="day in days"
                                :key="day.key"
                                :value="day.key"
                            >
                                {{ day.label }}
                            </option>
                        </select>
                    </div>
                </div>
                <div v-if="frequency !== 'none'" class="space-y-2">
                    <p class="text-sm font-medium">Ends</p>
                    <label class="mr-4 text-sm"
                        ><input v-model="endMode" value="never" type="radio" />
                        Never</label
                    ><label class="mr-4 text-sm"
                        ><input v-model="endMode" value="count" type="radio" />
                        After</label
                    ><input
                        v-if="endMode === 'count'"
                        v-model.number="count"
                        type="number"
                        min="1"
                        class="ml-2 w-20 rounded-md border bg-background p-2"
                    /><label class="mr-4 text-sm"
                        ><input v-model="endMode" value="until" type="radio" />
                        On date</label
                    ><input
                        v-if="endMode === 'until'"
                        v-model="until"
                        type="date"
                        class="mt-2 rounded-md border bg-background p-2"
                    />
                </div>
                <p class="rounded-md bg-muted p-3 text-sm">{{ summary }}</p>
            </div>
            <DialogFooter
                ><button
                    type="button"
                    class="cursor-pointer rounded-md border px-4 py-2"
                    @click="open = false"
                >
                    Cancel</button
                ><button
                    type="button"
                    class="cursor-pointer rounded-md bg-primary px-4 py-2 text-primary-foreground"
                    @click="apply"
                >
                    Apply schedule
                </button></DialogFooter
            ></DialogContent
        ></Dialog
    >
</template>
