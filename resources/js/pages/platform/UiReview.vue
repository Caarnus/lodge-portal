<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import AppearanceTabs from "@/components/AppearanceTabs.vue";
import InputError from "@/components/InputError.vue";
import PageHeader from "@/components/PageHeader.vue";
import TabBar from "@/components/TabBar.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from "@/components/ui/collapsible";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Progress, ProgressSummary } from "@/components/ui/progress";
import { Skeleton } from "@/components/ui/skeleton";
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from "@/components/ui/tooltip";
import {
    Check,
    ChevronDown,
    CornerDownRight,
    Eye,
    GripVertical,
    MoreHorizontal,
    Package,
    Pencil,
    Plus,
    ShoppingCart,
    Trash2,
    Rocket,
} from "lucide-vue-next";
import { ref } from "vue";

defineOptions({ layout: AppLayout });

const checked = ref(false);
const name = ref("");
const reviewSectionOpen = ref(true);
const reviewTabs = [
    { key: "overview", label: "Overview", href: "/platform/ui-review" },
    { key: "members", label: "Members", href: "/platform/ui-review" },
    { key: "settings", label: "Settings", href: "/platform/ui-review" },
];
const lodges = [
    {
        id: 1,
        name: "Example Lodge No. 42",
        location: "Evansville, Indiana",
        type: "Blue lodge",
        status: "Active",
    },
    {
        id: 2,
        name: "Frontier Research Lodge",
        location: "Indianapolis, Indiana",
        type: "Research lodge",
        status: "Draft",
    },
    {
        id: 3,
        name: "Wabash Valley Lodge No. 17",
        location: "Terre Haute, Indiana",
        type: "Blue lodge",
        status: "Active",
    },
    {
        id: 4,
        name: "River City Lodge No. 88",
        location: "New Albany, Indiana",
        type: "Blue lodge",
        status: "Inactive",
    },
];
const navigationRows = [
    {
        title: "Home",
        path: "/",
        hierarchy: "Top-level page",
        depth: 0,
        audience: "Public menu",
        status: "Published",
    },
    {
        title: "About our lodge",
        path: "/about",
        hierarchy: "Top-level page",
        depth: 0,
        audience: "Public menu",
        status: "Draft changes",
    },
    {
        title: "Lodge history",
        path: "/history",
        hierarchy: "Nested under About our lodge",
        depth: 1,
        audience: "Public menu",
        status: "Published",
    },
];
const storeProducts = [
    {
        name: "Founders Day commemorative coin",
        description: "Antique bronze finish with presentation case.",
        price: "$18.00",
        inventory: "24 available",
        available: true,
    },
    {
        name: "Lodge history digital edition",
        description: "Downloadable PDF delivered after checkout.",
        price: "$8.00",
        inventory: "Digital delivery",
        available: true,
    },
    {
        name: "Past Masters dinner ticket",
        description: "Admission for one guest. Sales close October 10.",
        price: "$35.00",
        inventory: "Sold out",
        available: false,
    },
];

const statusVariant = (status: string): "default" | "secondary" | "muted" =>
    status === "Active"
        ? "default"
        : status === "Draft"
          ? "secondary"
          : "muted";
</script>

<template>
    <main class="mx-auto w-full max-w-6xl space-y-10 p-4 sm:p-6 lg:p-8">
        <PageHeader
            eyebrow="Platform reference"
            title="UI review"
            description="Live reference for portal patterns. Use before new screen work; update shared UI components here first."
        >
            <template #actions>
                <div class="space-y-1">
                    <p
                        class="text-right text-xs font-medium text-muted-foreground"
                    >
                        Compare appearance
                    </p>
                    <AppearanceTabs />
                </div>
            </template>
        </PageHeader>

        <section class="space-y-4" aria-labelledby="foundation-heading">
            <div>
                <h2 id="foundation-heading" class="text-xl font-semibold">
                    Design foundations
                </h2>
                <p class="text-sm text-muted-foreground">
                    Semantic colors keep every feature consistent across light
                    and dark modes.
                </p>
            </div>
            <Card
                ><CardContent
                    class="grid gap-3 p-6 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div
                        class="rounded-md border border-border/80 bg-background p-4"
                    >
                        <div
                            class="mb-3 h-8 rounded bg-background ring-1 ring-border/60"
                        ></div>
                        <p class="text-sm font-medium">Background</p>
                        <p class="text-xs text-muted-foreground">Page canvas</p>
                    </div>
                    <div class="rounded-md border border-border/80 bg-card p-4">
                        <div
                            class="mb-3 h-8 rounded bg-card ring-1 ring-border/60"
                        ></div>
                        <p class="text-sm font-medium">Card</p>
                        <p class="text-xs text-muted-foreground">
                            Grouped content
                        </p>
                    </div>
                    <div class="rounded-md border border-border/80 bg-card p-4">
                        <div class="mb-3 h-8 rounded bg-primary"></div>
                        <p class="text-sm font-medium">Primary</p>
                        <p class="text-xs text-muted-foreground">
                            Main actions and links
                        </p>
                    </div>
                    <div class="rounded-md border border-border/80 bg-card p-4">
                        <div class="mb-3 h-8 rounded bg-muted"></div>
                        <p class="text-sm font-medium">Muted</p>
                        <p class="text-xs text-muted-foreground">
                            Quiet grouping and copy
                        </p>
                    </div>
                </CardContent></Card
            >
        </section>

        <section class="space-y-4" aria-labelledby="type-heading">
            <div>
                <h2 id="type-heading" class="text-xl font-semibold">
                    Typography and status
                </h2>
                <p class="text-sm text-muted-foreground">
                    Page hierarchy, supporting copy, feedback.
                </p>
            </div>
            <Card
                ><CardContent class="grid gap-6 p-6 md:grid-cols-2">
                    <div class="space-y-3">
                        <h1 class="text-2xl font-bold">Page title</h1>
                        <h2 class="text-xl font-semibold">Section heading</h2>
                        <p class="text-sm text-muted-foreground">
                            Supporting text uses muted foreground.
                        </p>
                        <a
                            href="#controls"
                            class="text-sm font-medium text-primary underline-offset-4 hover:underline"
                            >Text link</a
                        >
                    </div>
                    <div class="space-y-3">
                        <div class="flex flex-wrap gap-2">
                            <Badge>Active</Badge
                            ><Badge variant="secondary">Draft</Badge
                            ><Badge variant="muted">Inactive</Badge
                            ><Badge variant="warning">Pending</Badge
                            ><Badge variant="destructive"
                                >Needs attention</Badge
                            >
                        </div>
                        <div
                            class="rounded-md border border-destructive/20 bg-destructive/10 p-3 text-sm text-destructive"
                        >
                            Destructive feedback: say what failed and how to
                            recover.
                        </div>
                    </div>
                </CardContent></Card
            >
        </section>

        <section
            id="controls"
            class="space-y-4"
            aria-labelledby="controls-heading"
        >
            <div>
                <h2 id="controls-heading" class="text-xl font-semibold">
                    Actions and controls
                </h2>
                <p class="text-sm text-muted-foreground">
                    Primary action once per screen. Use outline for neutral
                    action.
                </p>
            </div>
            <Card
                ><CardContent class="space-y-6 p-6">
                    <div class="flex flex-wrap gap-3">
                        <Button><Plus /> Create lodge</Button
                        ><Button variant="outline">Save draft</Button
                        ><Button variant="secondary">Secondary</Button
                        ><Button variant="ghost">Ghost</Button
                        ><Button variant="destructive"><Trash2 /> Delete</Button
                        ><Button size="sm">Small</Button
                        ><Button size="icon" aria-label="More actions"
                            ><MoreHorizontal /></Button
                        ><Button disabled>Disabled</Button>
                    </div>
                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="review-name">Lodge name</Label
                            ><Input
                                id="review-name"
                                v-model="name"
                                placeholder="Example Lodge No. 42"
                            />
                            <p class="text-xs text-muted-foreground">
                                Helpful text describes expected value.
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label for="review-invalid">Meeting schedule</Label
                            ><Input
                                id="review-invalid"
                                aria-invalid="true"
                                class="border-destructive focus-visible:ring-destructive"
                                value=""
                                placeholder="First Tuesday"
                            /><InputError
                                message="Meeting schedule is required."
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="review-select">Lodge type</Label
                            ><select
                                id="review-select"
                                class="flex h-10 w-full rounded-md border border-input bg-card px-3 py-2 text-sm shadow-sm focus-visible:border-ring focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/35"
                            >
                                <option>Blue lodge</option>
                                <option>Research lodge</option>
                            </select>
                        </div>
                        <label
                            class="flex items-center gap-3 rounded-md border border-border/80 bg-card p-3 text-sm"
                            ><Checkbox v-model:checked="checked" />
                            <span
                                ><span class="block font-medium"
                                    >Publish page</span
                                ><span class="text-muted-foreground"
                                    >Visible to public visitors.</span
                                ></span
                            ></label
                        >
                    </div>
                </CardContent></Card
            >
        </section>

        <section
            class="space-y-4"
            aria-labelledby="navigation-patterns-heading"
        >
            <div>
                <h2
                    id="navigation-patterns-heading"
                    class="text-xl font-semibold"
                >
                    Navigation and disclosure
                </h2>
                <p class="text-sm text-muted-foreground">
                    Tabs switch between peer views. Collapsible sections reduce
                    scanning effort within a long page.
                </p>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Tabbed workspace</CardTitle>
                        <CardDescription>
                            Keep the active view visible and allow labels to
                            scroll horizontally on phones.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <TabBar
                            :tabs="reviewTabs"
                            active="overview"
                            ariaLabel="Example workspace"
                        />
                        <div class="pt-5 text-sm text-muted-foreground">
                            Overview content appears here.
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Collapsible section</CardTitle>
                        <CardDescription>
                            Use for optional or repeated page sections, not to
                            hide required validation feedback.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Collapsible v-model:open="reviewSectionOpen">
                            <div
                                class="overflow-hidden rounded-md border border-border/80 bg-card"
                            >
                                <CollapsibleTrigger as-child>
                                    <button
                                        type="button"
                                        class="flex w-full items-center justify-between gap-3 p-4 text-left"
                                    >
                                        <span>
                                            <span class="block font-medium"
                                                >Category details</span
                                            >
                                            <span
                                                class="block text-sm text-muted-foreground"
                                                >Three records</span
                                            >
                                        </span>
                                        <ChevronDown
                                            class="size-4 shrink-0 transition-transform"
                                            :class="
                                                reviewSectionOpen &&
                                                'rotate-180'
                                            "
                                        />
                                    </button>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <div
                                        class="border-t border-border/70 bg-muted/30 p-4 text-sm"
                                    >
                                        Repeated controls or supporting detail
                                        appear here.
                                    </div>
                                </CollapsibleContent>
                            </div>
                        </Collapsible>
                    </CardContent>
                </Card>
            </div>
            <Card>
                <CardHeader>
                    <CardTitle>Navigation tree editor</CardTitle>
                    <CardDescription>
                        Keep hierarchy, page state, drop targets, and actions
                        legible without turning the editor into a dense table.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        class="grid gap-3 rounded-md border border-border/70 bg-muted/30 p-3 text-sm sm:grid-cols-2"
                    >
                        <div class="flex items-start gap-2">
                            <GripVertical
                                class="mt-0.5 size-4 text-muted-foreground"
                            />
                            <span>
                                <strong class="font-medium"
                                    >Reorder pages</strong
                                >
                                <span class="block text-muted-foreground"
                                    >Drag to a line between pages.</span
                                >
                            </span>
                        </div>
                        <div class="flex items-start gap-2">
                            <CornerDownRight
                                class="mt-0.5 size-4 text-muted-foreground"
                            />
                            <span>
                                <strong class="font-medium"
                                    >Create a submenu</strong
                                >
                                <span class="block text-muted-foreground"
                                    >Drop directly onto a page.</span
                                >
                            </span>
                        </div>
                    </div>
                    <div
                        class="rounded-lg border border-border/80 bg-muted/20 p-2"
                    >
                        <template
                            v-for="(page, index) in navigationRows"
                            :key="page.title"
                        >
                            <div
                                v-if="index === 2"
                                class="flex h-8 items-center gap-2 rounded bg-primary/15 px-3 text-primary ring-1 ring-inset ring-primary/40"
                                :style="{
                                    marginLeft: `${page.depth * 1.5}rem`,
                                }"
                            >
                                <span class="h-px flex-1 bg-primary/40" />
                                <span class="text-xs font-semibold"
                                    >Move here</span
                                >
                                <span class="h-px flex-1 bg-primary/40" />
                            </div>
                            <div v-else class="h-1" />
                            <article
                                class="grid min-w-0 gap-3 rounded-md border border-border/80 bg-card p-3 shadow-[0_1px_2px_rgb(15_23_42/0.04)] sm:grid-cols-[minmax(0,1fr)_7rem] sm:items-center"
                            >
                                <div class="flex min-w-0 items-start gap-2">
                                    <div
                                        class="flex h-10 shrink-0 items-center"
                                        :style="{
                                            marginLeft: `${page.depth * 1.5}rem`,
                                        }"
                                    >
                                        <CornerDownRight
                                            v-if="page.depth"
                                            class="mr-1 size-4 text-muted-foreground"
                                        />
                                        <GripVertical
                                            class="size-5 text-muted-foreground"
                                        />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-medium">
                                            {{ page.title }}
                                        </p>
                                        <p
                                            class="truncate text-xs text-muted-foreground"
                                        >
                                            {{ page.hierarchy }} ·
                                            {{ page.path }}
                                        </p>
                                        <div
                                            class="mt-2 flex flex-wrap gap-1.5"
                                        >
                                            <Badge variant="muted">
                                                {{ page.audience }}
                                            </Badge>
                                            <Badge
                                                :variant="
                                                    page.status ===
                                                    'Draft changes'
                                                        ? 'warning'
                                                        : 'default'
                                                "
                                            >
                                                {{ page.status }}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    class="flex justify-end gap-1 border-t border-border/60 pt-3 sm:border-0 sm:pt-0"
                                >
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        :aria-label="`Preview ${page.title}`"
                                    >
                                        <Eye />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        :aria-label="`Edit ${page.title}`"
                                    >
                                        <Pencil />
                                    </Button>
                                </div>
                            </article>
                        </template>
                        <div class="h-1" />
                    </div>
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border/70 bg-muted/30 p-3"
                    >
                        <span class="text-sm font-medium text-primary">
                            Unsaved navigation changes
                        </span>
                        <div class="flex flex-wrap gap-2">
                            <Button variant="outline" size="sm">
                                Discard changes
                            </Button>
                            <Button size="sm">
                                <Rocket /> Save and publish navigation
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </section>

        <section class="space-y-4" aria-labelledby="content-heading">
            <div>
                <h2 id="content-heading" class="text-xl font-semibold">
                    Content containers
                </h2>
                <p class="text-sm text-muted-foreground">
                    Cards for grouped content; desktop rows with phone cards for
                    record lists.
                </p>
            </div>
            <div class="grid gap-4 md:grid-cols-3">
                <Card
                    ><CardHeader
                        ><CardTitle>Card title</CardTitle
                        ><CardDescription
                            >Short description explains
                            content.</CardDescription
                        ></CardHeader
                    ><CardContent class="text-sm"
                        >Keep content scan-friendly.</CardContent
                    ><CardFooter
                        ><Button variant="outline" size="sm"
                            >View details</Button
                        ></CardFooter
                    ></Card
                ><Card class="border-primary/30"
                    ><CardHeader
                        ><CardTitle>Selected card</CardTitle
                        ><CardDescription
                            >Use border change sparingly.</CardDescription
                        ></CardHeader
                    ><CardContent class="text-sm"
                        >Avoid alternate shadows or radii.</CardContent
                    ></Card
                ><Card
                    ><CardContent class="space-y-3 p-6"
                        ><Skeleton class="h-4 w-2/5" /><Skeleton
                            class="h-4 w-full" /><Skeleton
                            class="h-4 w-4/5" /></CardContent
                ></Card>
            </div>
            <Card class="overflow-hidden"
                ><CardHeader
                    class="flex-row items-center justify-between space-y-0 border-b border-border/70"
                    ><div>
                        <CardTitle>Data table</CardTitle
                        ><CardDescription
                            >Tablet table and phone cards use same data and
                            actions.</CardDescription
                        >
                    </div>
                    <Button size="sm"><Plus /> Add lodge</Button></CardHeader
                ><CardContent class="p-0"
                    ><div
                        class="hidden grid-cols-[minmax(12rem,1fr)_10rem_9rem_7rem] gap-4 border-b border-border/70 bg-muted/50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground md:grid"
                    >
                        <span>Lodge</span><span>Type</span><span>Status</span
                        ><span class="text-right">Actions</span>
                    </div>
                    <template v-for="lodge in lodges" :key="lodge.id"
                        ><div
                            class="hidden grid-cols-[minmax(12rem,1fr)_10rem_9rem_7rem] items-center gap-4 border-b border-border/60 px-4 py-4 transition-colors last:border-0 hover:bg-muted/35 md:grid"
                        >
                            <div>
                                <p class="font-medium">{{ lodge.name }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ lodge.location }}
                                </p>
                            </div>
                            <span class="text-sm">{{ lodge.type }}</span
                            ><Badge :variant="statusVariant(lodge.status)">{{
                                lodge.status
                            }}</Badge>
                            <div class="flex justify-end gap-2">
                                <Button variant="outline" size="sm">Edit</Button
                                ><DropdownMenu
                                    ><DropdownMenuTrigger as-child
                                        ><Button
                                            variant="ghost"
                                            size="icon"
                                            :aria-label="`More actions for ${lodge.name}`"
                                            ><MoreHorizontal /></Button></DropdownMenuTrigger
                                    ><DropdownMenuContent align="end"
                                        ><DropdownMenuItem
                                            >View details</DropdownMenuItem
                                        ><DropdownMenuItem
                                            >Duplicate</DropdownMenuItem
                                        ><DropdownMenuSeparator /><DropdownMenuItem
                                            class="text-destructive"
                                            >Archive</DropdownMenuItem
                                        ></DropdownMenuContent
                                    ></DropdownMenu
                                >
                            </div>
                        </div>
                        <div
                            class="space-y-3 border-b border-border/60 p-4 last:border-0 md:hidden"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-medium">{{ lodge.name }}</p>
                                    <p class="text-sm text-muted-foreground">
                                        {{ lodge.location }}
                                    </p>
                                </div>
                                <Badge :variant="statusVariant(lodge.status)">{{
                                    lodge.status
                                }}</Badge>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-muted-foreground">{{
                                    lodge.type
                                }}</span>
                                <div class="flex gap-2">
                                    <Button variant="outline" size="sm"
                                        >Edit</Button
                                    ><DropdownMenu
                                        ><DropdownMenuTrigger as-child
                                            ><Button
                                                variant="ghost"
                                                size="icon"
                                                :aria-label="`More actions for ${lodge.name}`"
                                                ><MoreHorizontal /></Button></DropdownMenuTrigger
                                        ><DropdownMenuContent align="end"
                                            ><DropdownMenuItem
                                                >View details</DropdownMenuItem
                                            ><DropdownMenuItem
                                                >Duplicate</DropdownMenuItem
                                            ><DropdownMenuSeparator /><DropdownMenuItem
                                                class="text-destructive"
                                                >Archive</DropdownMenuItem
                                            ></DropdownMenuContent
                                        ></DropdownMenu
                                    >
                                </div>
                            </div>
                        </div></template
                    ></CardContent
                ></Card
            >
        </section>

        <section class="space-y-4" aria-labelledby="progress-heading">
            <div>
                <h2 id="progress-heading" class="text-xl font-semibold">
                    Progress and goals
                </h2>
                <p class="text-sm text-muted-foreground">
                    Use the same accessible progress primitive for percentages,
                    fundraising totals, quotas, and completion states.
                </p>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Percentage complete</CardTitle>
                        <CardDescription>
                            Pair the bar with a visible value and a concise
                            explanation.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <ProgressSummary
                            label="Directory profiles completed"
                            :value="68"
                            description="102 of 150 member profiles include the required details."
                        />
                        <div class="space-y-2">
                            <div
                                class="flex justify-between gap-4 text-sm font-medium"
                            >
                                <span>Newsletter delivery</span>
                                <span class="tabular-nums">42%</span>
                            </div>
                            <Progress
                                :model-value="42"
                                aria-label="Newsletter delivery"
                            />
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Fundraising goal</CardTitle>
                        <CardDescription>
                            Supply a formatted value while the component keeps
                            the underlying numeric range accessible.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ProgressSummary
                            label="Temple restoration fund"
                            :value="18750"
                            :max="25000"
                            value-label="$18,750 of $25,000"
                            description="75% funded · $6,250 remaining"
                        />
                    </CardContent>
                    <CardFooter class="justify-between gap-3 border-t">
                        <span class="text-sm text-muted-foreground"
                            >Campaign ends December 31</span
                        >
                        <Button size="sm">Contribute</Button>
                    </CardFooter>
                </Card>
            </div>
        </section>

        <section class="space-y-4" aria-labelledby="storefront-heading">
            <div>
                <h2 id="storefront-heading" class="text-xl font-semibold">
                    Digital storefront
                </h2>
                <p class="text-sm text-muted-foreground">
                    Product discovery, availability, pricing, fulfillment, and
                    checkout should remain clear at phone and tablet widths.
                </p>
            </div>
            <Card>
                <CardHeader
                    class="flex-row flex-wrap items-center justify-between gap-3 space-y-0 border-b border-border/70"
                >
                    <div>
                        <CardTitle>Lodge store</CardTitle>
                        <CardDescription>
                            Merchandise, tickets, and digital products.
                        </CardDescription>
                    </div>
                    <Button variant="outline" size="sm">
                        <ShoppingCart /> Cart · 2
                    </Button>
                </CardHeader>
                <CardContent
                    class="grid gap-6 p-4 sm:p-6 lg:grid-cols-[minmax(0,1fr)_18rem]"
                >
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <article
                            v-for="product in storeProducts"
                            :key="product.name"
                            class="flex flex-col overflow-hidden rounded-lg border border-border/80 bg-card"
                        >
                            <div
                                class="flex aspect-[4/3] items-center justify-center bg-muted/45"
                            >
                                <Package
                                    class="size-10 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            </div>
                            <div class="flex flex-1 flex-col gap-3 p-4">
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <h3 class="font-medium leading-snug">
                                        {{ product.name }}
                                    </h3>
                                    <span
                                        class="shrink-0 font-semibold tabular-nums"
                                    >
                                        {{ product.price }}
                                    </span>
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    {{ product.description }}
                                </p>
                                <Badge
                                    class="mt-auto w-fit"
                                    :variant="
                                        product.available
                                            ? 'secondary'
                                            : 'muted'
                                    "
                                >
                                    {{ product.inventory }}
                                </Badge>
                                <Button
                                    class="w-full"
                                    size="sm"
                                    :variant="
                                        product.available
                                            ? 'default'
                                            : 'secondary'
                                    "
                                    :disabled="!product.available"
                                >
                                    {{
                                        product.available
                                            ? "Add to cart"
                                            : "Unavailable"
                                    }}
                                </Button>
                            </div>
                        </article>
                    </div>
                    <aside
                        class="h-fit rounded-lg border border-border/80 bg-muted/25 p-4"
                    >
                        <h3 class="font-semibold">Order summary</h3>
                        <div class="mt-4 space-y-3 text-sm">
                            <div class="flex justify-between gap-3">
                                <span class="text-muted-foreground"
                                    >Commemorative coin × 1</span
                                >
                                <span class="tabular-nums">$18.00</span>
                            </div>
                            <div class="flex justify-between gap-3">
                                <span class="text-muted-foreground"
                                    >Digital lodge history × 1</span
                                >
                                <span class="tabular-nums">$8.00</span>
                            </div>
                            <div
                                class="flex justify-between gap-3 border-t border-border/70 pt-3 font-semibold"
                            >
                                <span>Total</span>
                                <span class="tabular-nums">$26.00</span>
                            </div>
                        </div>
                        <p class="mt-4 text-xs text-muted-foreground">
                            Physical items are available for lodge pickup.
                            Digital items are emailed after payment.
                        </p>
                        <Button class="mt-4 w-full">Checkout</Button>
                    </aside>
                </CardContent>
            </Card>
        </section>

        <section class="space-y-4" aria-labelledby="overlays-heading">
            <div>
                <h2 id="overlays-heading" class="text-xl font-semibold">
                    Overlays and empty state
                </h2>
                <p class="text-sm text-muted-foreground">
                    Use shared accessible primitives for temporary actions and
                    help.
                </p>
            </div>
            <Card
                ><CardContent class="flex flex-wrap items-center gap-3 p-6"
                    ><Dialog
                        ><DialogTrigger as-child
                            ><Button>Open dialog</Button></DialogTrigger
                        ><DialogContent
                            ><DialogHeader
                                ><DialogTitle>Confirm archive</DialogTitle
                                ><DialogDescription
                                    >Archive hides group from discovery.
                                    Existing memberships stay in
                                    history.</DialogDescription
                                ></DialogHeader
                            ><DialogFooter
                                ><Button variant="outline">Cancel</Button
                                ><Button variant="destructive"
                                    >Archive group</Button
                                ></DialogFooter
                            ></DialogContent
                        ></Dialog
                    ><DropdownMenu
                        ><DropdownMenuTrigger as-child
                            ><Button variant="outline"
                                >More actions
                                <ChevronDown /></Button></DropdownMenuTrigger
                        ><DropdownMenuContent
                            ><DropdownMenuLabel>Group actions</DropdownMenuLabel
                            ><DropdownMenuSeparator /><DropdownMenuItem
                                >Edit group</DropdownMenuItem
                            ><DropdownMenuItem class="text-destructive"
                                >Archive group</DropdownMenuItem
                            ></DropdownMenuContent
                        ></DropdownMenu
                    ><TooltipProvider
                        ><Tooltip
                            ><TooltipTrigger as-child
                                ><Button
                                    variant="outline"
                                    size="icon"
                                    aria-label="Help"
                                    >?</Button
                                ></TooltipTrigger
                            ><TooltipContent
                                >Explain uncommon controls.</TooltipContent
                            ></Tooltip
                        ></TooltipProvider
                    ></CardContent
                ></Card
            >
            <Card
                ><CardContent
                    class="flex min-h-48 flex-col items-center justify-center gap-3 p-6 text-center"
                    ><Check class="size-8 text-muted-foreground" />
                    <div>
                        <p class="font-medium">No groups found</p>
                        <p class="text-sm text-muted-foreground">
                            Change filters or create first group.
                        </p>
                    </div>
                    <Button variant="outline"
                        >Clear filters</Button
                    ></CardContent
                ></Card
            >
        </section>
    </main>
</template>
