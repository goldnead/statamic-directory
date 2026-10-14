<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@statamic/cms/inertia';
import {
    Badge,
    Button,
    CommandPaletteItem,
    ConfirmationModal,
    Description,
    DropdownItem,
    EmptyStateItem,
    EmptyStateMenu,
    Header,
    Icon,
    Listing,
} from '@statamic/cms/ui';

const props = defineProps({
    columns: { type: Array, required: true },
    filters: { type: Array, required: true },
    listingUrl: { type: String, required: true },
    rebuildUrl: { type: String, required: true },
    statusUrl: { type: String, required: true },
    rebuild: { type: Object, default: null },
    canManage: { type: Boolean, default: false },
    perPage: { type: Number, default: 25 },
    hasAny: { type: Boolean, default: false },
    setupRequired: { type: Boolean, default: false },
});

const status = ref(props.rebuild);
const busy = ref(false);
const unbinding = ref(null);
const listing = ref(null);
let timer = null;

const active = computed(() => ['queued', 'running'].includes(status.value?.state));

const statusText = computed(() => {
    if (!status.value) return null;
    const { state, processed = 0, total = 0 } = status.value;

    return __(`directory::cp.rebuild_status_${state}`, { processed, total });
});

const statusColor = computed(() => ({ done: 'green', failed: 'red' })[status.value?.state] ?? 'gray');

async function poll() {
    try {
        const response = await fetch(props.statusUrl, { headers: { Accept: 'application/json' } });
        const next = (await response.json()).rebuild;
        const wasActive = active.value;
        status.value = next;
        if (wasActive && !active.value) {
            router.reload();
        }
    } catch (e) {
        stop();
    }
}

function start() {
    if (timer === null) timer = setInterval(poll, 2000);
}

function stop() {
    clearInterval(timer);
    timer = null;
}

watch(active, (isActive) => (isActive ? start() : stop()), { immediate: false });
onMounted(() => active.value && start());
onBeforeUnmount(stop);

function post(url) {
    busy.value = true;
    router.post(url, {}, {
        preserveScroll: true,
        onSuccess: () => {
            status.value = { state: 'queued' };
            start();
        },
        onFinish: () => {
            busy.value = false;
            unbinding.value = null;
        },
    });
}

function unbind() {
    busy.value = true;
    router.post(unbinding.value.unbind_url, {}, {
        preserveScroll: true,
        onFinish: () => {
            busy.value = false;
            unbinding.value = null;
            router.reload();
        },
    });
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString(Statamic.$config.get('locale')?.replace('_', '-')) : null;
}
</script>

<template>
    <Head :title="__('directory::cp.nav')" />

    <div class="max-w-page mx-auto" data-max-width-wrapper>
        <template v-if="!hasAny">
            <!-- Core's empty state is a centred h1 rather than <Header>; see pages/forms/Index.vue. -->
            <header class="py-8 pt-16 text-center">
                <h1 class="text-[25px] font-medium antialiased flex justify-center items-center gap-2 sm:gap-3">
                    <Icon name="map-search" class="size-5 text-gray-500" />{{ __('directory::cp.nav') }}
                </h1>
            </header>

            <EmptyStateMenu :heading="setupRequired ? __('directory::cp.setup_required') : __('directory::cp.empty_heading')">
                <EmptyStateItem
                    v-if="canManage && !setupRequired"
                    icon="sync"
                    :heading="__('directory::cp.empty_rebuild_heading')"
                    :description="__('directory::cp.empty_rebuild_description')"
                    @click="post(rebuildUrl)"
                />
            </EmptyStateMenu>
        </template>

        <template v-else>
            <Header :title="__('directory::cp.nav')" icon="map-search">
                <CommandPaletteItem
                    v-if="canManage"
                    category="Actions"
                    :text="__('directory::cp.rebuild_all')"
                    icon="sync"
                    :action="() => post(rebuildUrl)"
                    prioritize
                    v-slot="{ text }"
                >
                    <Button :text="text" variant="primary" :loading="busy || active" @click="post(rebuildUrl)" />
                </CommandPaletteItem>
            </Header>

            <Description :text="__('directory::cp.description')" class="mb-4" />

            <div v-if="statusText" class="mb-4 flex items-center gap-2">
                <Badge pill :color="statusColor" :text="statusText" />
            </div>

            <Listing
                ref="listing"
                :url="listingUrl"
                :columns="columns"
                :filters="filters"
                :per-page="perPage"
                preferences-prefix="directory"
                sort-column="title"
                sort-direction="asc"
                push-query
            >
                <template #cell-title="{ row }">
                    <Link v-if="row.edit_url" :href="row.edit_url" class="whitespace-nowrap">{{ row.title }}</Link>
                    <span v-else class="whitespace-nowrap">{{ row.title }}</span>
                </template>

                <template #cell-collection="{ row }">
                    <span class="whitespace-nowrap">{{ row.collection_title }}</span>
                </template>

                <template #cell-visibility="{ row }">
                    <Badge
                        pill
                        :color="row.visibility === 'public' ? 'green' : 'gray'"
                        :text="row.visibility === 'public' ? __('directory::cp.visibility_public') : __('directory::cp.visibility_private')"
                    />
                </template>

                <template #cell-hidden="{ row }">
                    <Badge v-if="row.hidden" pill color="amber" :text="__('directory::cp.yes')" />
                    <span v-else class="text-gray-500 dark:text-gray-400">&mdash;</span>
                </template>

                <template #cell-team="{ row }">
                    <Badge v-if="row.team" pill color="green" :text="__('directory::cp.yes')" />
                    <span v-else class="text-gray-500 dark:text-gray-400">&mdash;</span>
                </template>

                <template #cell-coordinates="{ row }">
                    <Badge v-if="row.coordinates" pill color="green" :text="__('directory::cp.yes')" />
                    <Badge v-else pill color="amber" :text="__('directory::cp.no')" />
                </template>

                <template #cell-updated_at="{ row }">
                    <span class="whitespace-nowrap">{{ formatDate(row.updated_at) }}</span>
                </template>

                <template #prepended-row-actions="{ row }">
                    <DropdownItem
                        v-if="row.rebuild_url"
                        :text="__('directory::cp.rebuild_profile')"
                        icon="sync"
                        @click="post(row.rebuild_url)"
                    />
                    <DropdownItem
                        v-if="row.unbind_url"
                        :text="__('directory::cp.unbind')"
                        icon="link"
                        variant="destructive"
                        @click="unbinding = row"
                    />
                </template>
            </Listing>
        </template>

        <ConfirmationModal
            :open="unbinding !== null"
            :title="__('directory::cp.unbind')"
            :button-text="__('directory::cp.unbind')"
            :cancel-text="__('directory::cp.cancel')"
            :busy="busy"
            danger
            @update:open="(open) => { if (!open && !busy) unbinding = null; }"
            @cancel="unbinding = null"
            @confirm="unbind"
        >
            <Description :text="__('directory::cp.unbind_confirm')" />
        </ConfirmationModal>
    </div>
</template>
