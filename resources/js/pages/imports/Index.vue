<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import TableBuilder from '@table-builder/components/TableBuilder.vue';
import type { TableData } from '@table-builder/types/table-builder';
import MailingLayout from '@mailing/components/MailingLayout.vue';
import { useBreadcrumbs } from '@mailing/composables/useBreadcrumbs';
import { useConfirm } from '@mailing/composables/useConfirm';
import { useErrors } from '@mailing/composables/useErrors';
import type { MailingBreadcrumb } from '@mailing/types';
import { ui } from '@mailing/ui';

const props = defineProps<{
    imports: TableData;
    createUrl: string;
    createDisabledReason: string | null;
    breadcrumbs: MailingBreadcrumb[];
}>();

useBreadcrumbs(() => props.breadcrumbs);

const confirm = useConfirm();

const errors = useErrors(['import']);
</script>

<template>
    <Head :title="$t('mailing::imports.index.title')" />

    <MailingLayout>
        <div class="flex flex-col gap-6">
            <div class="flex items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h1 :class="ui.title">
                        {{ $t('mailing::imports.index.title') }}
                    </h1>
                    <p :class="ui.muted">
                        {{ $t('mailing::imports.index.description') }}
                    </p>
                </div>

                <span
                    v-if="createDisabledReason !== null"
                    :class="ui.primaryButton"
                    class="cursor-not-allowed opacity-50"
                    :title="createDisabledReason"
                    aria-disabled="true"
                >
                    {{ $t('mailing::imports.actions.new') }}
                </span>
                <Link v-else :href="createUrl" :class="ui.primaryButton">
                    {{ $t('mailing::imports.actions.new') }}
                </Link>
            </div>

            <p v-for="error in errors" :key="error" :class="ui.error">
                {{ error }}
            </p>

            <TableBuilder :table="imports" :confirm="confirm" />
        </div>
    </MailingLayout>
</template>
