<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";

import HeadingSmall from "@/components/HeadingSmall.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import AppLayout from "@/layouts/AppLayout.vue";
import type { BreadcrumbItem } from "@/types";

interface Person {
    id: number;
    display_name: string;
    email: string | null;
    phone: string | null;
    address: {
        line_1: string | null;
        line_2: string | null;
        city: string | null;
        state: string | null;
        postal_code: string | null;
    } | null;
    degree: string | null;
    profile_photo_url: string | null;
}

interface Props {
    lodge: { id: number; name: string; number: string | null };
    people: {
        data: Person[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: {
        audience: "own_lodge" | "participating_lodges";
        query: string;
        degree: number | null;
    };
    degrees: Array<{ id: number; name: string }>;
}

const props = defineProps<Props>();
const query = ref(props.filters.query);
const audience = ref(props.filters.audience);
const degree = ref(props.filters.degree ? String(props.filters.degree) : "");
let debounce: ReturnType<typeof setTimeout> | undefined;
const breadcrumbs: BreadcrumbItem[] = [
    { title: "Directory", href: `/lodges/${props.lodge.id}/directory` },
];
const countText = computed(
    () =>
        `${props.people.total} member${props.people.total === 1 ? "" : "s"} found`,
);

const visit = (page?: number) => {
    router.get(
        `/lodges/${props.lodge.id}/directory`,
        {
            audience: audience.value,
            query: query.value || undefined,
            degree: degree.value || undefined,
            page,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

watch([query, audience, degree], () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => visit(), 300);
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${lodge.name} directory`" />
        <main class="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-6">
            <HeadingSmall
                :title="`${lodge.name} directory`"
                description="Find members using the audience and contact choices they have shared."
            />

            <form
                class="grid gap-4 rounded-lg border p-4 md:grid-cols-[minmax(0,1fr)_13rem_13rem]"
                @submit.prevent="visit()"
            >
                <div class="grid gap-2">
                    <Label for="directory-search">Search members</Label
                    ><Input
                        id="directory-search"
                        v-model="query"
                        autocomplete="off"
                        placeholder="Name, shared email, or shared phone"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="directory-audience">Audience</Label
                    ><select
                        id="directory-audience"
                        v-model="audience"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="own_lodge">My lodge</option>
                        <option value="participating_lodges">
                            Participating lodges
                        </option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label for="directory-degree">Degree</Label
                    ><select
                        id="directory-degree"
                        v-model="degree"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">All degrees</option>
                        <option
                            v-for="item in degrees"
                            :key="item.id"
                            :value="String(item.id)"
                        >
                            {{ item.name }}
                        </option>
                    </select>
                </div>
            </form>

            <p class="text-sm text-muted-foreground" aria-live="polite">
                {{ countText }}
            </p>
            <div
                v-if="people.data.length"
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <article
                    v-for="person in people.data"
                    :key="person.id"
                    class="rounded-lg border p-4"
                >
                    <div class="flex gap-3">
                        <img
                            v-if="person.profile_photo_url"
                            :src="person.profile_photo_url"
                            :alt="`${person.display_name} profile photo`"
                            class="size-12 rounded-full object-cover"
                        />
                        <div
                            v-else
                            class="flex size-12 items-center justify-center rounded-full bg-muted text-sm"
                            aria-hidden="true"
                        >
                            {{ person.display_name.slice(0, 1) }}
                        </div>
                        <div>
                            <h2 class="font-medium">
                                <Link
                                    :href="
                                        route('lodges.directory.show', {
                                            lodge: lodge.id,
                                            person: person.id,
                                            audience,
                                        })
                                    "
                                    class="underline-offset-4 hover:underline"
                                    >{{ person.display_name }}</Link
                                >
                            </h2>
                            <p
                                v-if="person.degree"
                                class="text-sm text-muted-foreground"
                            >
                                {{ person.degree }}
                            </p>
                        </div>
                    </div>
                    <dl class="mt-4 space-y-2 text-sm">
                        <div v-if="person.email">
                            <dt class="sr-only">Email</dt>
                            <dd>{{ person.email }}</dd>
                        </div>
                        <div v-if="person.phone">
                            <dt class="sr-only">Phone</dt>
                            <dd>{{ person.phone }}</dd>
                        </div>
                        <div v-if="person.address">
                            <dt class="sr-only">Mailing address</dt>
                            <dd>
                                {{
                                    [
                                        person.address.line_1,
                                        person.address.line_2,
                                        [
                                            person.address.city,
                                            person.address.state,
                                        ]
                                            .filter(Boolean)
                                            .join(", "),
                                        person.address.postal_code,
                                    ]
                                        .filter(Boolean)
                                        .join(", ")
                                }}
                            </dd>
                        </div>
                    </dl>
                </article>
            </div>
            <p
                v-else
                class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                No members match this directory view.
            </p>
            <nav
                v-if="people.last_page > 1"
                class="flex items-center justify-between"
                aria-label="Directory pages"
            >
                <Button
                    variant="outline"
                    :disabled="!people.prev_page_url"
                    @click="visit(people.current_page - 1)"
                    >Previous</Button
                ><span class="text-sm"
                    >Page {{ people.current_page }} of
                    {{ people.last_page }}</span
                ><Button
                    variant="outline"
                    :disabled="!people.next_page_url"
                    @click="visit(people.current_page + 1)"
                    >Next</Button
                >
            </nav>
        </main>
    </AppLayout>
</template>
