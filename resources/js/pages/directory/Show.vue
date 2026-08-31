<script lang="ts" setup>
import {Head, Link} from "@inertiajs/vue3";

import HeadingSmall from "@/components/HeadingSmall.vue";
import {Badge} from "@/components/ui/badge";
import {Card, CardContent} from "@/components/ui/card";
import AppLayout from "@/layouts/AppLayout.vue";
import type {BreadcrumbItem} from "@/types";

interface Props {
    lodge: { id: number; name: string; number: string | null };
    audience: "own_lodge" | "participating_lodges";
    person: {
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
        affiliations: Array<{
            id: number;
            name: string;
            number: string;
            slug: string;
        }>;
    };
}

const props = defineProps<Props>();
const breadcrumbs: BreadcrumbItem[] = [
    {title: "Directory", href: `/lodges/${props.lodge.id}/directory`},
    {title: props.person.display_name, href: "#"},
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${person.display_name} · Directory`"/>
        <main class="mx-auto w-full max-w-3xl space-y-6 p-4 md:p-6">
            <Link
                :href="
                    route('lodges.directory.index', {
                        lodge: lodge.id,
                        audience,
                    })
                "
                class="text-sm underline underline-offset-4"
            >Back to directory
            </Link
            >
            <Card
            >
                <CardContent class="p-6">
                    <div class="flex items-center gap-4">
                        <img
                            v-if="person.profile_photo_url"
                            :alt="`${person.display_name} profile photo`"
                            :src="person.profile_photo_url"
                            class="size-20 rounded-full object-cover"
                        />
                        <div>
                            <HeadingSmall
                                :description="
                                    person.degree ?? 'Directory member'
                                "
                                :title="person.display_name"
                            />
                        </div>
                    </div>
                    <dl class="mt-6 space-y-4 text-sm">
                        <div
                            v-if="
                                audience === 'participating_lodges' &&
                                person.affiliations.length
                            "
                        >
                            <dt class="font-medium">WorkingTools lodges</dt>
                            <dd class="mt-1 flex flex-wrap gap-1">
                                <Badge
                                    v-for="affiliation in person.affiliations"
                                    :key="affiliation.id"
                                    variant="secondary"
                                >
                                    {{ affiliation.name }} No.
                                    {{ affiliation.number }}
                                </Badge>
                            </dd>
                        </div>
                        <div v-if="person.email">
                            <dt class="font-medium">Email</dt>
                            <dd>{{ person.email }}</dd>
                        </div>
                        <div v-if="person.phone">
                            <dt class="font-medium">Phone</dt>
                            <dd>{{ person.phone }}</dd>
                        </div>
                        <div v-if="person.address">
                            <dt class="font-medium">Mailing address</dt>
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
                </CardContent>
            </Card
            >
        </main>
    </AppLayout>
</template>
