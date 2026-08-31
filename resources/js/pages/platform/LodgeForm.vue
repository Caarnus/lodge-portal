<script lang="ts" setup>
import {Head, Link, useForm} from "@inertiajs/vue3";
import AppLayout from "@/layouts/AppLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import WorkspaceTabs from "@/components/WorkspaceTabs.vue";

defineOptions({layout: AppLayout});
const p = defineProps<{
    lodge?: any;
    action?: string;
    admins?: Array<any>;
    features?: Array<any>;
}>();
const l = p.lodge ?? {};
const form = useForm({
    name: l.name ?? "",
    number: l.number ?? "",
    slug: l.slug ?? "",
    city: l.city ?? "",
    state: l.state ?? "IN",
    jurisdiction: l.jurisdiction ?? "Indiana",
    physical_address: l.physical_address ?? "",
    mailing_address: l.mailing_address ?? "",
    meeting_location: l.meeting_location ?? "",
    meeting_schedule: l.meeting_schedule ?? "",
    timezone: l.timezone ?? "America/Chicago",
    date_display_format: l.date_display_format ?? "day_month_year",
    public_email: l.public_email ?? "",
    public_phone: l.public_phone ?? "",
    contact_email: l.contact_email ?? "",
    status: l.status ?? "active",
    primary_color: l.primary_color ?? "#1E3A5F",
    secondary_color: l.secondary_color ?? "#D4AF37",
    logo: null as File | null,
});
const admin = useForm({email: "", name: ""});
const flags = useForm({
    features: (p.features ?? []).filter((f) => f.enabled).map((f) => f.id),
});

function submit() {
    if (p.lodge)
        form.transform((d) => ({...d, _method: "put"})).post(
            p.action ?? `/platform/lodges/${p.lodge.id}`,
            {forceFormData: true},
        );
    else form.post("/platform/lodges", {forceFormData: true});
}
</script>
<template>
    <Head :title="lodge ? 'Edit lodge' : 'Create lodge'"/>
    <main
        :class="action ? 'max-w-6xl' : 'max-w-3xl'"
        class="mx-auto w-full p-4 sm:p-6 lg:p-8"
    >
        <PageHeader
            :description="
                lodge
                    ? 'Manage lodge identity, public contact details, and branding.'
                    : 'Set up the lodge identity, public contact details, and branding.'
            "
            :title="lodge ? 'Edit lodge' : 'Create lodge'"
        >
            <template #actions
            >
                <Link
                    v-if="lodge"
                    :href="`/lodges/${lodge.id}/website`"
                    class="secondary-button"
                >Manage website
                </Link
                >
            </template
            >
        </PageHeader>
        <WorkspaceTabs
            v-if="lodge && action"
            :lodge="lodge"
            active="lodge"
            class="mt-6"
            workspace="settings"
        />
        <form
            class="mt-6 grid gap-5 rounded-lg border border-border/80 bg-card p-4 sm:grid-cols-2 sm:p-6"
            @submit.prevent="submit"
        >
            <label
                v-for="f in [
                    'name',
                    'number',
                    'slug',
                    'city',
                    'state',
                    'jurisdiction',
                    'physical_address',
                    'mailing_address',
                    'meeting_location',
                    'meeting_schedule',
                    'timezone',
                    'public_email',
                    'public_phone',
                ]"
                :key="f"
                class="grid gap-1"
            ><span class="capitalize">{{ f.replaceAll("_", " ") }}</span
            ><input
                v-model="form[f]"
                :required="
                        ![
                            'mailing_address',
                            'meeting_location',
                            'meeting_schedule',
                            'public_phone',
                        ].includes(f)
                    "
                class="field-input"
            /><small class="text-destructive">{{
                    form.errors[f]
                }}</small></label
            ><label class="field-label"
        >Contact form recipient email<input
            v-model="form.contact_email"
            class="field-input"
            type="email"
        /><small class="text-destructive">{{
                form.errors.contact_email
            }}</small
        ><small class="text-muted-foreground"
        >Leave blank to use the public email address.</small
        ></label
        ><label class="field-label"
        >Status<select v-model="form.status" class="field-input">
            <option value="active">Active</option>
            <option value="disabled">Disabled</option>
            <option value="disabled_locked">Disabled and locked</option>
        </select></label
        ><label class="field-label"
        >Date display<select
            v-model="form.date_display_format"
            class="field-input"
        >
            <option value="month_year">Month, YYYY</option>
            <option value="month_day_year">Month D, YYYY</option>
            <option value="day_month_year">D Month YYYY</option>
        </select></label
        ><label class="field-label"
        >Logo<input
            accept="image/*"
            class="file-input"
            type="file"
            @change="
                        form.logo =
                            ($event.target as HTMLInputElement).files?.[0] ??
                            null
                    "/></label
        ><label class="field-label"
        >Primary color<input
            v-model="form.primary_color"
            class="h-10 w-full cursor-pointer rounded-md border border-input bg-card p-1"
            type="color"/></label
        ><label class="field-label"
        >Secondary color<input
            v-model="form.secondary_color"
            class="h-10 w-full cursor-pointer rounded-md border border-input bg-card p-1"
            type="color"/></label
        >
            <button
                :disabled="form.processing"
                class="primary-button sm:col-span-2"
            >
                Save lodge
            </button>
        </form>
        <section
            v-if="lodge && !action"
            class="mt-10 rounded-lg border border-border/80 bg-card p-4 sm:p-6"
        >
            <h2 class="text-xl font-semibold">Lodge administrators</h2>
            <ul class="my-3 space-y-1">
                <li v-for="a in admins" :key="a.id">
                    {{ a.name }} — {{ a.email }}
                </li>
                <li
                    v-if="!admins?.length"
                    class="text-sm text-muted-foreground"
                >
                    No lodge administrators assigned.
                </li>
            </ul>
            <form
                class="grid gap-3 sm:grid-cols-2"
                @submit.prevent="
                    admin.post(`/platform/lodges/${lodge.id}/admins`)
                "
            >
                <input
                    v-model="admin.name"
                    class="field-input"
                    placeholder="Name (for a new user)"
                /><input
                v-model="admin.email"
                class="field-input"
                placeholder="Email"
                required
                type="email"
            />
                <button
                    :disabled="admin.processing"
                    class="primary-button sm:col-span-2"
                >
                    Assign or create administrator
                </button>
            </form>
        </section>
        <section
            v-if="lodge && !action"
            class="mt-10 rounded-lg border border-border/80 bg-card p-4 sm:p-6"
        >
            <h2 class="text-xl font-semibold">Features</h2>
            <p v-if="!features?.length" class="mt-2 text-muted-foreground">
                No release features are currently defined.
            </p>
            <form
                v-else
                class="mt-3"
                @submit.prevent="
                    flags.put(`/platform/lodges/${lodge.id}/features`)
                "
            >
                <label
                    v-for="f in features"
                    :key="f.id"
                    class="mt-2 flex items-center gap-2 rounded-md border border-border/80 bg-muted/30 p-3 first:mt-0"
                ><input
                    v-model="flags.features"
                    :value="f.id"
                    type="checkbox"
                />{{ f.name }}</label
                >
                <button
                    :disabled="flags.processing"
                    class="primary-button mt-3"
                >
                    Save features
                </button>
            </form>
        </section>
        <slot/>
    </main>
</template>
