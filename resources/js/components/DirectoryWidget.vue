<script setup>
import { Badge, Widget } from '@statamic/cms/ui';

defineProps({
    title: { type: String, required: true },
    url: { type: String, required: true },
    types: { type: Array, default: () => [] },
});
</script>

<template>
    <Widget :title="title" icon="map-search" :href="url">
        <p
            v-if="types.every((type) => type.total === 0)"
            class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400"
        >
            {{ __('directory::cp.widget_empty') }}
        </p>

        <ul v-else class="divide-y divide-gray-200 dark:divide-gray-800">
            <li v-for="type in types" :key="type.handle" class="flex flex-col gap-2 px-4 py-3">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-sm font-medium">{{ type.title }}</span>
                    <span class="text-sm tabular-nums text-gray-500 dark:text-gray-400">
                        {{ __('directory::cp.widget_total') }}: {{ type.total }}
                    </span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Badge pill color="green" :text="`${__('directory::cp.widget_public')}: ${type.public}`" />
                    <Badge pill color="gray" :text="`${__('directory::cp.widget_private')}: ${type.private}`" />
                    <Badge
                        pill
                        :color="type.no_coordinates > 0 ? 'amber' : 'gray'"
                        :text="`${__('directory::cp.widget_no_coordinates')}: ${type.no_coordinates}`"
                    />
                </div>
            </li>
        </ul>
    </Widget>
</template>
