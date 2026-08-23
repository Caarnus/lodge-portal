<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
import { formatLodgeDate } from "@/utils/date";

const props = defineProps<{
    section: any;
    lodge: any;
    media: Record<string, any>;
    officers: any[];
    pastMasters: any[];
    events: any[];
    galleries: any[];
    newsletters: any[];
    memberContent: { directory: boolean; newsletters: boolean };
    primaryForeground: string;
    secondaryForeground: string;
}>();
const asset = (id: number | null | undefined) =>
    id ? props.media[String(id)] : null;
const formatDate = (value: string | null) =>
    formatLodgeDate(value, props.lodge.date_display_format);
const contactForm = useForm({
    name: "",
    email: "",
    message: "",
    website: "",
});
const sendContact = () =>
    contactForm.post(`/l/${props.lodge.slug}/contact`, {
        preserveScroll: true,
        onSuccess: () => contactForm.reset(),
    });
</script>

<template>
    <section
        v-if="section.type === 'hero'"
        class="relative isolate overflow-hidden px-5 py-20 text-center sm:py-28"
        :style="{
            backgroundColor: lodge.primary_color,
            color: asset(section.configuration.media_id)
                ? '#ffffff'
                : primaryForeground,
        }"
    >
        <img
            v-if="asset(section.configuration.media_id)"
            :src="asset(section.configuration.media_id).url"
            :alt="asset(section.configuration.media_id).alt_text"
            class="absolute inset-0 -z-20 size-full object-cover"
        />
        <div
            v-if="asset(section.configuration.media_id)"
            class="absolute inset-0 -z-10 bg-black/55"
        ></div>
        <h1 class="text-4xl font-bold sm:text-6xl">
            {{ section.configuration.heading }}
        </h1>
        <p
            v-if="section.configuration.body"
            class="mx-auto mt-5 max-w-3xl text-lg"
        >
            {{ section.configuration.body }}
        </p>
    </section>
    <section
        v-else-if="
            section.type === 'rich_text' || section.type === 'custom_html'
        "
        class="public-rich-text mx-auto max-w-4xl px-5 py-10"
        v-html="section.configuration.html"
    ></section>
    <figure
        v-else-if="section.type === 'image'"
        class="mx-auto max-w-5xl px-5 py-10"
    >
        <img
            v-if="asset(section.configuration.media_id)"
            :src="asset(section.configuration.media_id).url"
            :alt="asset(section.configuration.media_id).alt_text"
            class="w-full rounded-xl object-cover shadow-sm"
        />
        <figcaption
            v-if="section.configuration.caption"
            class="mt-3 text-center text-sm text-slate-600"
        >
            {{ section.configuration.caption }}
        </figcaption>
    </figure>
    <section
        v-else-if="section.type === 'image_text'"
        class="mx-auto grid max-w-6xl items-center gap-8 px-5 py-12 md:grid-cols-2"
    >
        <img
            v-if="asset(section.configuration.media_id)"
            :src="asset(section.configuration.media_id).url"
            :alt="asset(section.configuration.media_id).alt_text"
            class="w-full rounded-xl object-cover"
            :class="{
                'md:order-2': section.configuration.image_side === 'right',
            }"
        />
        <div>
            <h2 class="text-3xl font-bold">
                {{ section.configuration.heading }}
            </h2>
            <p class="mt-4 whitespace-pre-line text-slate-700">
                {{ section.configuration.body }}
            </p>
        </div>
    </section>
    <section
        v-else-if="section.type === 'link_list'"
        class="mx-auto max-w-4xl px-5 py-10"
    >
        <h2 v-if="section.configuration.heading" class="text-3xl font-bold">
            {{ section.configuration.heading }}
        </h2>
        <ul class="mt-4 divide-y rounded-lg border">
            <li v-for="link in section.configuration.links" :key="link.url">
                <a
                    :href="link.url"
                    class="block px-4 py-3 font-medium hover:bg-slate-50"
                    >{{ link.label }} →</a
                >
            </li>
        </ul>
    </section>
    <section
        v-else-if="section.type === 'call_to_action'"
        class="mx-auto my-10 max-w-5xl rounded-xl px-6 py-10 text-center"
        :style="{
            backgroundColor: lodge.secondary_color,
            color: secondaryForeground,
        }"
    >
        <h2 class="text-3xl font-bold">{{ section.configuration.heading }}</h2>
        <p v-if="section.configuration.body" class="mx-auto mt-3 max-w-2xl">
            {{ section.configuration.body }}
        </p>
        <a
            :href="section.configuration.url"
            class="mt-6 inline-block rounded-md px-5 py-3 font-semibold"
            :style="{
                backgroundColor: lodge.primary_color,
                color: primaryForeground,
            }"
            >{{ section.configuration.label }}</a
        >
    </section>
    <section
        v-else-if="section.type === 'contact_information'"
        class="mx-auto max-w-4xl px-5 py-10"
    >
        <h2 class="text-3xl font-bold">
            {{ section.configuration.heading || "Contact Us" }}
        </h2>
        <p v-if="section.configuration.body" class="mt-3">
            {{ section.configuration.body }}
        </p>
        <address class="mt-5 not-italic">
            <p>
                {{ lodge.physical_address }}, {{ lodge.city }},
                {{ lodge.state }}
            </p>
            <p>
                <a :href="`mailto:${lodge.public_email}`" class="underline">{{
                    lodge.public_email
                }}</a>
            </p>
            <p v-if="lodge.public_phone">
                <a :href="`tel:${lodge.public_phone}`" class="underline">{{
                    lodge.public_phone
                }}</a>
            </p>
        </address>
        <form
            v-if="section.configuration.show_contact_form"
            class="mt-8 grid max-w-2xl gap-4 rounded-xl border p-5"
            @submit.prevent="sendContact"
        >
            <h3 class="text-xl font-semibold">Send us a message</h3>
            <label class="grid gap-1 text-sm font-medium"
                >Name<input
                    v-model="contactForm.name"
                    autocomplete="name"
                    required
                    class="rounded-md border px-3 py-2"
                /><span
                    v-if="contactForm.errors.name"
                    class="text-sm text-red-700"
                    >{{ contactForm.errors.name }}</span
                ></label
            >
            <label class="grid gap-1 text-sm font-medium"
                >Email<input
                    v-model="contactForm.email"
                    type="email"
                    autocomplete="email"
                    required
                    class="rounded-md border px-3 py-2"
                /><span
                    v-if="contactForm.errors.email"
                    class="text-sm text-red-700"
                    >{{ contactForm.errors.email }}</span
                ></label
            >
            <label class="grid gap-1 text-sm font-medium"
                >Message<textarea
                    v-model="contactForm.message"
                    required
                    rows="5"
                    class="rounded-md border px-3 py-2"
                ></textarea
                ><span
                    v-if="contactForm.errors.message"
                    class="text-sm text-red-700"
                    >{{ contactForm.errors.message }}</span
                ></label
            >
            <input
                v-model="contactForm.website"
                aria-hidden="true"
                autocomplete="off"
                tabindex="-1"
                class="hidden"
            />
            <p
                v-if="contactForm.recentlySuccessful"
                class="text-sm text-emerald-700"
            >
                Thank you. Your message has been sent.
            </p>
            <button
                :disabled="contactForm.processing"
                class="w-fit rounded-md px-4 py-2 font-medium"
                :style="{
                    backgroundColor: lodge.primary_color,
                    color: primaryForeground,
                }"
            >
                Send message
            </button>
        </form>
    </section>
    <section
        v-else-if="section.type === 'meeting_information'"
        class="mx-auto max-w-4xl px-5 py-10"
    >
        <h2 class="text-3xl font-bold">
            {{ section.configuration.heading || "Meeting Information" }}
        </h2>
        <p v-if="lodge.meeting_location" class="mt-4 font-medium">
            {{ lodge.meeting_location }}
        </p>
        <p v-if="section.configuration.body" class="mt-3">
            {{ section.configuration.body }}
        </p>
    </section>
    <section
        v-else-if="section.type === 'officers_placeholder'"
        class="mx-auto max-w-5xl px-5 py-10"
    >
        <h2 class="text-3xl font-bold">
            {{ section.configuration.heading || "Lodge Officers" }}
        </h2>
        <div
            v-if="officers.length"
            class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
        >
            <article
                v-for="officer in officers"
                :key="`${officer.position}-${officer.name}`"
                class="rounded-xl border p-5"
            >
                <p
                    class="text-sm font-semibold uppercase tracking-wide text-slate-500"
                >
                    {{ officer.position }}
                </p>
                <h3 class="mt-1 text-xl font-bold">{{ officer.name }}</h3>
                <p v-if="officer.email" class="mt-3">
                    <a :href="`mailto:${officer.email}`" class="underline">{{
                        officer.email
                    }}</a>
                </p>
                <p v-if="officer.phone">
                    <a :href="`tel:${officer.phone}`" class="underline">{{
                        officer.phone
                    }}</a>
                </p>
            </article>
        </div>
        <p v-else class="mt-4 text-slate-600">
            {{
                section.configuration.body ||
                "Officer information will be available soon."
            }}
        </p>
    </section>
    <section
        v-else-if="section.type === 'past_masters_placeholder'"
        class="mx-auto max-w-4xl px-5 py-10"
    >
        <h2 class="text-3xl font-bold">
            {{ section.configuration.heading || "Past Masters" }}
        </h2>
        <p
            v-if="section.configuration.body"
            class="mt-3 max-w-2xl text-slate-600"
        >
            {{ section.configuration.body }}
        </p>
        <ol
            v-if="pastMasters.length"
            class="mt-6 overflow-hidden rounded-xl border"
        >
            <li
                v-for="term in pastMasters"
                :key="`${term.year}-${term.name}`"
                class="grid grid-cols-[5rem_minmax(0,1fr)] items-center gap-4 border-b px-5 py-4 last:border-b-0"
            >
                <span class="font-mono text-lg font-semibold text-slate-500">{{
                    term.year
                }}</span
                ><span class="text-lg font-medium">{{ term.name }}</span>
            </li>
        </ol>
        <p v-else class="mt-4 text-slate-600">
            Past Master records will be added soon.
        </p>
    </section>
    <section
        v-else-if="section.type === 'events_placeholder'"
        class="mx-auto max-w-4xl px-5 py-10"
    >
        <h2 class="text-3xl font-bold">
            {{ section.configuration.heading || "Upcoming Events" }}
        </h2>
        <p v-if="section.configuration.body" class="mt-3 text-slate-600">
            {{ section.configuration.body }}
        </p>
        <ol
            v-if="
                events
                    .filter(
                        (event) =>
                            !section.configuration.event_category_id ||
                            event.event_category_id ===
                                section.configuration.event_category_id,
                    )
                    .slice(0, section.configuration.maximum_items ?? 6).length
            "
            class="mt-6 divide-y rounded-xl border"
        >
            <li
                v-for="event in events
                    .filter(
                        (event) =>
                            !section.configuration.event_category_id ||
                            event.event_category_id ===
                                section.configuration.event_category_id,
                    )
                    .slice(0, section.configuration.maximum_items ?? 6)"
                :key="event.id"
                class="px-5 py-4"
            >
                <a
                    :href="`/l/${lodge.slug}/events/${event.id}`"
                    class="font-medium underline"
                    >{{ event.title }}</a
                >
                <p class="text-sm text-slate-600">
                    {{ new Date(event.starts_at).toLocaleString() }}
                </p>
            </li>
        </ol>
        <p v-else class="mt-4 text-slate-600">
            {{ section.configuration.body || "No upcoming events." }}
        </p>
        <a
            v-if="section.configuration.show_all_link"
            :href="`/l/${lodge.slug}/events`"
            class="mt-5 inline-block font-medium underline"
            >View all events</a
        >
    </section>
    <section
        v-else-if="section.type === 'newsletter_placeholder'"
        class="mx-auto max-w-6xl px-5 py-10"
    >
        <div class="text-center">
            <h2 class="text-2xl font-bold">
                {{ section.configuration.heading || "Member newsletters" }}
            </h2>
            <p class="mt-3 text-slate-600">
                {{
                    section.configuration.body ||
                    "Read the latest member newsletters."
                }}
            </p>
        </div>
        <div
            v-if="newsletters.length"
            class="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
        >
            <a
                v-for="newsletter in newsletters"
                :key="newsletter.slug"
                :href="`/l/${lodge.slug}/newsletters/${newsletter.slug}`"
                class="group overflow-hidden rounded-xl border bg-card text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
            >
                <img
                    v-if="newsletter.has_cover"
                    :src="`/lodges/${lodge.id}/newsletters/${newsletter.slug}/cover`"
                    alt=""
                    class="aspect-[16/9] w-full object-cover"
                />
                <div class="p-5">
                    <h3 class="font-semibold group-hover:underline">
                        {{ newsletter.title }}
                    </h3>
                    <p
                        v-if="newsletter.publication_date"
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        {{ formatDate(newsletter.publication_date) }}
                    </p>
                </div>
            </a>
        </div>
        <p v-else class="mt-6 text-center text-muted-foreground">
            No newsletters have been published yet.
        </p>
    </section>
    <section
        v-else-if="section.type === 'directory_placeholder'"
        class="mx-auto max-w-4xl px-5 py-10"
    >
        <div
            class="rounded-xl border border-dashed bg-slate-50 p-8 text-center"
        >
            <h2 class="text-2xl font-bold">
                {{ section.configuration.heading || "Member directory" }}
            </h2>
            <p class="mt-3 text-slate-600">
                {{
                    section.configuration.body || "Search the member directory."
                }}
            </p>
            <a
                :href="`/lodges/${lodge.id}/directory`"
                class="mt-5 inline-block font-medium underline"
                >Open directory</a
            >
        </div>
    </section>
    <section
        v-else-if="section.type === 'gallery_placeholder'"
        class="mx-auto max-w-5xl px-5 py-10"
    >
        <h2 class="text-3xl font-bold">
            {{ section.configuration.heading || "Gallery" }}
        </h2>
        <div
            v-if="galleries.length"
            class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
        >
            <a
                v-for="album in galleries"
                :key="album.slug"
                :href="`/l/${lodge.slug}/galleries/${album.slug}`"
                class="rounded-xl border p-4 hover:bg-slate-50"
                ><strong>{{ album.title }}</strong></a
            >
        </div>
        <p v-else class="mt-3 text-slate-600">
            {{ section.configuration.body || "Photos will be available soon." }}
        </p>
        <a
            :href="`/l/${lodge.slug}/galleries`"
            class="mt-5 inline-block underline"
            >View gallery</a
        >
    </section>
    <section v-else class="mx-auto max-w-4xl px-5 py-10">
        <div
            class="rounded-xl border border-dashed bg-slate-50 p-8 text-center"
        >
            <h2 class="text-2xl font-bold">
                {{ section.configuration.heading }}
            </h2>
            <p class="mt-3 text-slate-600">
                {{
                    section.configuration.body ||
                    "More information will be available soon."
                }}
            </p>
        </div>
    </section>
</template>
