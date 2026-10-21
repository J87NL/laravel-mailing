<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import TableBuilder from '@table-builder/components/TableBuilder.vue';
import type { TableData } from '@table-builder/types/table-builder';
import MailingLayout from '@mailing/components/MailingLayout.vue';
import { useBreadcrumbs } from '@mailing/composables/useBreadcrumbs';
import { useConfirm } from '@mailing/composables/useConfirm';
import type { MailingBreadcrumb } from '@mailing/types';
import { ui } from '@mailing/ui';

const props = defineProps<{
    suppressions: TableData;
    createUrl: string;
    breadcrumbs: MailingBreadcrumb[];
}>();

useBreadcrumbs(() => props.breadcrumbs);

const confirm = useConfirm();
</script>

<template>
    <Head :title="$t('mailing::suppressions.index.title')" />

    <MailingLayout>
        <div class="flex flex-col gap-6">
            <div class="flex items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h1 :class="ui.title">
                        {{ $t('mailing::suppressions.index.title') }}
                    </h1>
                    <p :class="ui.muted">
                        {{ $t('mailing::suppressions.index.description') }}
                    </p>
                </div>

                <Link :href="createUrl" :class="ui.primaryButton">
                    {{ $t('mailing::suppressions.create.title') }}
                </Link>
            </div>

            <TableBuilder :table="suppressions" :confirm="confirm" />
        </div>
    </MailingLayout>
</template>
