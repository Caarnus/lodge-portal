<script setup lang="ts">
import { TransitionRoot } from "@headlessui/vue";
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";

import DeleteUser from "@/components/DeleteUser.vue";
import HeadingSmall from "@/components/HeadingSmall.vue";
import InputError from "@/components/InputError.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import AppLayout from "@/layouts/AppLayout.vue";
import SettingsLayout from "@/layouts/settings/Layout.vue";
import { type AuthenticatedSharedData, type BreadcrumbItem } from "@/types";

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
    profile: {
        preferred_name: string | null;
        email: string;
        phone: string | null;
        mailing_address_line_1: string | null;
        mailing_address_line_2: string | null;
        mailing_city: string | null;
        mailing_state: string | null;
        mailing_postal_code: string | null;
    };
    directoryPrivacy: {
        scope: "hidden" | "own_lodge" | "participating_lodges";
        show_email: boolean;
        show_phone: boolean;
        show_address: boolean;
        show_profile_photo: boolean;
        show_degree: boolean;
    };
    communicationPreferences: Array<{
        membership_id: number;
        lodge_name: string;
        lodge_number: string | null;
        receives_lodge_email: boolean;
        receives_print_newsletter: boolean;
        has_complete_mailing_address: boolean;
    }>;
    photo: { status: string | null; error: string | null; ready: boolean };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: "Profile settings",
        href: "/settings/profile",
    },
];

const page = usePage<AuthenticatedSharedData>();
const user = page.props.auth.user;

const form = useForm({
    preferred_name: props.profile.preferred_name ?? "",
    email: props.profile.email,
    phone: props.profile.phone ?? "",
    mailing_address_line_1: props.profile.mailing_address_line_1 ?? "",
    mailing_address_line_2: props.profile.mailing_address_line_2 ?? "",
    mailing_city: props.profile.mailing_city ?? "",
    mailing_state: props.profile.mailing_state ?? "",
    mailing_postal_code: props.profile.mailing_postal_code ?? "",
});

const submit = () => {
    form.patch(route("profile.update"), {
        preserveScroll: true,
    });
};

const privacyForm = useForm({ ...props.directoryPrivacy });
const photoForm = useForm<{ photo: File | null }>({ photo: null });

const savePrivacy = () => {
    privacyForm.put(route("profile.directory-privacy.update"), {
        preserveScroll: true,
    });
};

const saveCommunication = (membershipId: number, receivesLodgeEmail: boolean, receivesPrintNewsletter: boolean) => {
    useForm({ receives_lodge_email: receivesLodgeEmail, receives_print_newsletter: receivesPrintNewsletter }).put(
        route("profile.communication-preference.update", { membership: membershipId }),
        { preserveScroll: true },
    );
};

const uploadPhoto = () => {
    photoForm.post(route("profile.photo.store"), { preserveScroll: true });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Profile settings" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    title="Profile information"
                    description="Update your preferred name and contact information. These changes update your shared member profile."
                />

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid gap-2">
                        <Label for="preferred_name">Preferred name</Label>
                        <Input
                            id="preferred_name"
                            class="mt-1 block w-full"
                            v-model="form.preferred_name"
                            autocomplete="given-name"
                            placeholder="Preferred first name"
                        />
                        <InputError
                            class="mt-2"
                            :message="form.errors.preferred_name"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            v-model="form.email"
                            required
                            autocomplete="username"
                            placeholder="Email address"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="phone">Phone</Label>
                        <Input
                            id="phone"
                            class="mt-1 block w-full"
                            v-model="form.phone"
                            autocomplete="tel"
                            placeholder="Phone number"
                        />
                        <InputError class="mt-2" :message="form.errors.phone" />
                    </div>

                    <div class="space-y-4">
                        <div>
                            <Label for="mailing_address_line_1"
                                >Mailing address</Label
                            >
                            <p class="mt-1 text-sm text-muted-foreground">
                                Address changes apply to your shared member
                                profile.
                            </p>
                        </div>
                        <div class="grid gap-2">
                            <Input
                                id="mailing_address_line_1"
                                v-model="form.mailing_address_line_1"
                                autocomplete="address-line1"
                                placeholder="Address line 1"
                            />
                            <InputError
                                :message="form.errors.mailing_address_line_1"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Input
                                id="mailing_address_line_2"
                                v-model="form.mailing_address_line_2"
                                autocomplete="address-line2"
                                placeholder="Address line 2"
                            />
                            <InputError
                                :message="form.errors.mailing_address_line_2"
                            />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-[1fr_5rem_8rem]">
                            <div class="grid gap-2">
                                <Label for="mailing_city">City</Label>
                                <Input
                                    id="mailing_city"
                                    v-model="form.mailing_city"
                                    autocomplete="address-level2"
                                />
                                <InputError
                                    :message="form.errors.mailing_city"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="mailing_state">State</Label>
                                <Input
                                    id="mailing_state"
                                    v-model="form.mailing_state"
                                    autocomplete="address-level1"
                                    maxlength="2"
                                />
                                <InputError
                                    :message="form.errors.mailing_state"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="mailing_postal_code">ZIP code</Label>
                                <Input
                                    id="mailing_postal_code"
                                    v-model="form.mailing_postal_code"
                                    autocomplete="postal-code"
                                />
                                <InputError
                                    :message="form.errors.mailing_postal_code"
                                />
                            </div>
                        </div>
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="mt-2 text-sm text-neutral-800">
                            Your email address is unverified.
                            <Link
                                :href="route('verification.send')"
                                method="post"
                                as="button"
                                class="focus:outline-hidden rounded-md text-sm text-neutral-600 underline hover:text-neutral-900 focus:ring-2 focus:ring-offset-2"
                            >
                                Click here to re-send the verification email.
                            </Link>
                        </p>

                        <div
                            v-if="status === 'verification-link-sent'"
                            class="mt-2 text-sm font-medium text-green-600"
                        >
                            A new verification link has been sent to your email
                            address.
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="form.processing">Save</Button>

                        <TransitionRoot
                            :show="form.recentlySuccessful"
                            enter="transition ease-in-out"
                            enter-from="opacity-0"
                            leave="transition ease-in-out"
                            leave-to="opacity-0"
                        >
                            <p class="text-sm text-neutral-600">Saved.</p>
                        </TransitionRoot>
                    </div>
                </form>
            </div>

            <section class="mt-10 space-y-6 border-t pt-8">
                <HeadingSmall
                    title="Directory privacy"
                    description="Control how your member profile appears in directories. Family information is never shared by the directory."
                />
                <form @submit.prevent="savePrivacy" class="space-y-5">
                    <fieldset class="space-y-3">
                        <legend class="text-sm font-medium">Directory visibility</legend>
                        <label class="flex gap-3 text-sm">
                            <input v-model="privacyForm.scope" value="hidden" type="radio" name="directory-scope" />
                            <span><strong>Hidden</strong><br />Removes you from ordinary directories, not authorized lodge records.</span>
                        </label>
                        <label class="flex gap-3 text-sm">
                            <input v-model="privacyForm.scope" value="own_lodge" type="radio" name="directory-scope" />
                            <span><strong>My lodges</strong><br />Visible to every lodge where you have a current active membership.</span>
                        </label>
                        <label class="flex gap-3 text-sm">
                            <input v-model="privacyForm.scope" value="participating_lodges" type="radio" name="directory-scope" />
                            <span><strong>Participating lodges</strong><br />Opt in to directory sharing across participating lodges.</span>
                        </label>
                        <InputError :message="privacyForm.errors.scope" />
                    </fieldset>
                    <fieldset class="space-y-2">
                        <legend class="text-sm font-medium">Optional directory fields</legend>
                        <p class="text-sm text-muted-foreground">These choices apply to both directory audiences.</p>
                        <label class="flex items-center gap-2 text-sm"><input v-model="privacyForm.show_email" type="checkbox" /> Email</label>
                        <label class="flex items-center gap-2 text-sm"><input v-model="privacyForm.show_phone" type="checkbox" /> Phone</label>
                        <label class="flex items-center gap-2 text-sm"><input v-model="privacyForm.show_address" type="checkbox" /> Mailing address</label>
                        <label class="flex items-center gap-2 text-sm"><input v-model="privacyForm.show_profile_photo" type="checkbox" /> Profile photo</label>
                        <label class="flex items-center gap-2 text-sm"><input v-model="privacyForm.show_degree" type="checkbox" /> Degree</label>
                    </fieldset>
                    <Button :disabled="privacyForm.processing">Save privacy</Button>
                </form>
            </section>

            <section class="mt-10 space-y-6 border-t pt-8">
                <HeadingSmall title="Lodge communications" description="Choose email and mailed-newsletter preferences for each active lodge membership." />
                <p v-if="communicationPreferences.length === 0" class="text-sm text-muted-foreground">You have no active lodge memberships.</p>
                <div v-for="preference in communicationPreferences" :key="preference.membership_id" class="flex flex-col gap-3 rounded-md border p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-medium">{{ preference.lodge_name }}<span v-if="preference.lodge_number"> · {{ preference.lodge_number }}</span></p>
                        <p class="text-sm text-muted-foreground">Lodge email for this membership.</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input :checked="preference.receives_lodge_email" type="checkbox" @change="saveCommunication(preference.membership_id, ($event.target as HTMLInputElement).checked, preference.receives_print_newsletter)" />
                        Receive email
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input :checked="preference.receives_print_newsletter" type="checkbox" :disabled="!preference.has_complete_mailing_address" @change="saveCommunication(preference.membership_id, preference.receives_lodge_email, ($event.target as HTMLInputElement).checked)" />
                        Mailed newsletter
                    </label>
                    <p v-if="!preference.has_complete_mailing_address" class="text-sm text-muted-foreground">Add a complete mailing address above to request a mailed copy.</p>
                </div>
            </section>

            <section class="mt-10 space-y-6 border-t pt-8">
                <HeadingSmall title="Profile photo" description="Your photo is stored privately and is processed before it can be shown." />
                <img v-if="photo.ready" :src="route('profile.photo.show')" alt="Your profile photo" class="size-24 rounded-full object-cover" />
                <p v-else-if="photo.status === 'pending' || photo.status === 'processing'" class="text-sm text-muted-foreground" aria-live="polite">Your photo is being prepared. Refresh this page in a moment.</p>
                <p v-else-if="photo.status === 'failed'" class="text-sm text-destructive" role="alert">Photo processing failed. Choose another image and try again.</p>
                <form @submit.prevent="uploadPhoto" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="grid gap-2">
                        <Label for="profile-photo">Choose a photo</Label>
                        <Input id="profile-photo" type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif" @input="photoForm.photo = ($event.target as HTMLInputElement).files?.[0] ?? null" />
                        <InputError :message="photoForm.errors.photo" />
                    </div>
                    <Button :disabled="photoForm.processing || !photoForm.photo">Upload photo</Button>
                    <Button v-if="photo.status" type="button" variant="outline" :disabled="photoForm.processing" @click="photoForm.delete(route('profile.photo.destroy'), { preserveScroll: true })">Remove photo</Button>
                </form>
            </section>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
