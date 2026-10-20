<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import TableBuilder from '@table-builder/components/TableBuilder.vue';
import type { TableData } from '@table-builder/types/table-builder';
import { useBreadcrumbs } from '@mailing/composables/useBreadcrumbs';
import { useConfirm } from '@mailing/composables/useConfirm';
import type {
    MailingBreadcrumb,
    MailingHistoryEntry,
    MailingSubscriber,
} from '@mailing/types';
import { ui } from '@mailing/ui';

const props = defineProps<{
    subscriber: MailingSubscriber;
    eraseUrl: string;
    subscriptions: TableData;
    history: MailingHistoryEntry[];
    breadcrumbs: MailingBreadcrumb[];
}>();

useBreadcrumbs(() => props.breadcrumbs);

const confirm = useConfirm();
</script>

<template>
    <Head :title="subscriber.email" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 :class="ui.title">{{ subscriber.email }}</h1>
                <p v-if="subscriber.name" :class="ui.muted">
                    {{ subscriber.name }}
                </p>
                <p v-if="subscriber.createdAt" :class="ui.muted">
                    {{
                        $t('mailing::subscribers.show.created_at', {
                            date: subscriber.createdAt,
                        })
                    }}
                </p>
            </div>

            <Link :href="eraseUrl" :class="ui.dangerButton">
                {{ $t('mailing::subscribers.erase.action') }}
            </Link>
        </div>

        <p v-if="subscriber.suppression" :class="ui.notice">
            {{
                $t('mailing::subscribers.show.suppressed', {
                    reason: subscriber.suppression.reason,
                    date: subscriber.suppression.createdAt ?? '',
                })
            }}
        </p>

        <section class="flex flex-col gap-3">
            <h2 :class="ui.heading">
                {{ $t('mailing::subscribers.show.subscriptions') }}
            </h2>
            <TableBuilder :table="subscriptions" :confirm="confirm" />
        </section>

        <section :class="ui.panel">
            <h2 :class="ui.heading">
                {{ $t('mailing::subscribers.show.history') }}
            </h2>

            <p v-if="history.length === 0" :class="ui.muted">
                {{ $t('mailing::subscribers.show.no_history') }}
            </p>

            <ol v-else class="flex flex-col gap-3">
                <li
                    v-for="entry in history"
                    :key="entry.id"
                    class="flex flex-col gap-0.5 border-l-2 border-gray-200 pl-3 dark:border-gray-700"
                >
                    <span class="text-sm font-medium">
                        {{ entry.action }}
                        <span v-if="entry.list" :class="ui.muted">
                            · {{ entry.list }}
                        </span>
                    </span>
                    <span :class="ui.muted">
                        {{ entry.at }}
                        <template v-if="entry.source">
                            · {{ entry.source }}
                        </template>
                        <template v-if="entry.ip"> · {{ entry.ip }}</template>
                    </span>
                    <span v-if="entry.note" class="text-sm">
                        {{ entry.note }}
                    </span>
                </li>
            </ol>
        </section>
    </div>
</template>
