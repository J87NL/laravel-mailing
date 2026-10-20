<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { MailingNavigationItem } from '@mailing/types';

const page = usePage();

const items = computed<MailingNavigationItem[]>(
    () =>
        (page.props.mailingNavigation as MailingNavigationItem[] | undefined) ??
        [],
);
</script>

<template>
    <div class="flex flex-col gap-4 p-4 md:flex-row md:gap-6">
        <nav
            v-if="items.length > 1"
            :aria-label="$t('mailing::navigation.sections')"
            class="flex shrink-0 gap-0.5 overflow-x-auto text-sm md:w-48 md:flex-col"
        >
            <Link
                v-for="item in items"
                :key="item.key"
                :href="item.href"
                :aria-current="item.active ? 'page' : undefined"
                class="rounded-md px-2 py-1.5 whitespace-nowrap text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800"
                :class="{
                    'bg-gray-100 font-medium dark:bg-gray-800': item.active,
                }"
            >
                {{ item.title }}
            </Link>
        </nav>

        <div class="min-w-0 flex-1">
            <slot />
        </div>
    </div>
</template>
