<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import { Card, CardContent } from "@/components/ui/card";
import { Head, Link } from "@inertiajs/vue3";

defineProps<{
    requestingLodge: { id: number; name: string };
    person: any;
    audience: string;
}>();
const dayName = (day: number) =>
    [
        "Monday",
        "Tuesday",
        "Wednesday",
        "Thursday",
        "Friday",
        "Saturday",
        "Sunday",
    ][day - 1];
</script>

<template>
    <Head :title="`${person.display_name} — Ritual Assistance`" />
    <AppLayout
        :breadcrumbs="[
            {
                title: 'Ritual Assistance',
                href: `/lodges/${requestingLodge.id}/ritual-assistance?audience=${audience}`,
            },
            { title: person.display_name, href: '#' },
        ]"
    >
        <main class="mx-auto w-full max-w-3xl space-y-5 p-4 md:p-6">
            <Link
                class="text-sm underline underline-offset-4"
                :href="`/lodges/${requestingLodge.id}/ritual-assistance?audience=${audience}`"
                >Back to results</Link
            ><Card
                ><CardContent class="p-5"
                    ><PageHeader
                        :title="person.display_name"
                        :description="
                            person.affiliations
                                .map(
                                    (item: any) =>
                                        `${item.name} · ${item.number}`,
                                )
                                .join(' • ')
                        "
                    />
                    <div class="mt-4 text-sm">
                        <a
                            v-if="person.email"
                            :href="`mailto:${person.email}`"
                            class="mr-4 underline"
                            >{{ person.email }}</a
                        ><a
                            v-if="person.phone"
                            :href="`tel:${person.phone}`"
                            class="underline"
                            >{{ person.phone }}</a
                        >
                    </div></CardContent
                ></Card
            ><Card
                ><CardContent class="p-5"
                    ><h2 class="font-semibold">
                        Self-reported proficient ritual parts
                    </h2>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li v-for="part in person.parts" :key="part.id">
                            <strong
                                >{{ part.category }} — {{ part.name }}</strong
                            ><span class="text-muted-foreground">
                                · Self-reported · Updated
                                {{
                                    new Date(
                                        part.updated_at,
                                    ).toLocaleDateString()
                                }}</span
                            >
                        </li>
                    </ul></CardContent
                ></Card
            ><Card
                v-if="
                    person.availability.length ||
                    person.public_availability_note
                "
                ><CardContent class="p-5"
                    ><h2 class="font-semibold">Broad availability</h2>
                    <p class="mt-2 text-sm">
                        {{
                            person.availability
                                .map(
                                    (item: any) =>
                                        `${dayName(item.day_of_week)} ${item.daypart}`,
                                )
                                .join(", ")
                        }}
                    </p>
                    <p
                        v-if="person.public_availability_note"
                        class="mt-2 text-sm text-muted-foreground"
                    >
                        {{ person.public_availability_note }}
                    </p>
                    <p class="mt-3 text-xs text-muted-foreground">
                        Availability is informational only. This member has not
                        accepted an assignment; contact him separately.
                    </p></CardContent
                ></Card
            >
        </main>
    </AppLayout>
</template>
