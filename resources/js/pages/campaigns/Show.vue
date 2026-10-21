<script setup lang="ts">
import Form from '@form-builder/components/Form.vue';
import type { FormSchema } from '@form-builder/types/form-builder';
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import TableBuilder from '@table-builder/components/TableBuilder.vue';
import type { TableData } from '@table-builder/types/table-builder';
import MailingLayout from '@mailing/components/MailingLayout.vue';
import { useBreadcrumbs } from '@mailing/composables/useBreadcrumbs';
import { useConfirm } from '@mailing/composables/useConfirm';
import { useErrors } from '@mailing/composables/useErrors';
import type {
    MailingAction,
    MailingAttachment,
    MailingBreadcrumb,
    MailingCampaign,
    MailingPreflightItem,
} from '@mailing/types';
import { ui } from '@mailing/ui';
import { watch } from 'vue';

const props = defineProps<{
    campaign: MailingCampaign;
    counts: { status: string; label: string; count: number }[];
    attachments: MailingAttachment[];
    preflight: MailingPreflightItem[];
    actions: MailingAction[];
    scheduleForm: FormSchema | null;
    scheduleBlockedReason: string | null;
    testForm: FormSchema | null;
    sends: TableData | null;
    breadcrumbs: MailingBreadcrumb[];
}>();

useBreadcrumbs(() => props.breadcrumbs);

const confirm = useConfirm();

const poll = usePoll(
    15000,
    {
        only: ['campaign', 'counts', 'actions', 'sends'],
    },
    {
        autoStart: false,
    },
);

watch(
    () => props.campaign.status,
    (status) => {
        if (status === 'sending') {
            poll.start();

            return;
        }

        poll.stop();
    },
    { immediate: true },
);

const errors = useErrors(['campaign', 'recipients']);

const statusClass: Record<MailingPreflightItem['status'], string> = {
    ok: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
    warning:
        'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-100',
    error: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
};

async function run(action: MailingAction): Promise<void> {
    if (action.disabledReason !== null) {
        return;
    }

    if (action.confirm !== null && !(await confirm(action.confirm))) {
        return;
    }

    router.visit(action.url, { method: action.method });
}
</script>

<template>
    <Head :title="campaign.subject" />

    <MailingLayout>
        <div class="flex flex-col gap-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h1 :class="ui.title">{{ campaign.subject }}</h1>
                    <p :class="ui.muted">
                        {{ campaign.list }} · {{ campaign.statusLabel }} ·
                        {{
                            $t('mailing::campaigns.show.recipients', {
                                count: String(campaign.recipients),
                            })
                        }}
                    </p>
                </div>

                <div :class="ui.toolbar">
                    <template v-for="action in actions" :key="action.label">
                        <Link
                            v-if="
                                action.method === 'get' &&
                                action.disabledReason === null
                            "
                            :href="action.url"
                            :class="ui.button"
                        >
                            {{ action.label }}
                        </Link>
                        <button
                            v-else
                            type="button"
                            :class="
                                action.variant === 'destructive'
                                    ? ui.dangerButton
                                    : ui.button
                            "
                            :disabled="action.disabledReason !== null"
                            :title="action.disabledReason ?? undefined"
                            @click="run(action)"
                        >
                            {{ action.label }}
                        </button>
                    </template>
                </div>
            </div>

            <p v-for="error in errors" :key="error" :class="ui.error">
                {{ error }}
            </p>

            <p :class="ui.notice">{{ campaign.transport }}</p>

            <section :class="ui.panel">
                <h2 :class="ui.heading">
                    {{ $t('mailing::campaigns.show.progress') }}
                </h2>
                <p v-if="campaign.scheduledAt" :class="ui.muted">
                    {{
                        $t('mailing::campaigns.show.scheduled', {
                            time: campaign.scheduledAt,
                        })
                    }}
                </p>
                <p v-if="campaign.startedAt" :class="ui.muted">
                    {{
                        $t('mailing::campaigns.show.started', {
                            time: campaign.startedAt,
                        })
                    }}
                </p>
                <p v-if="campaign.estimatedFinish" :class="ui.muted">
                    {{
                        $t('mailing::campaigns.show.estimated_finish', {
                            time: campaign.estimatedFinish,
                        })
                    }}
                </p>
                <p v-if="campaign.finishedAt" :class="ui.muted">
                    {{
                        $t('mailing::campaigns.show.finished', {
                            time: campaign.finishedAt,
                        })
                    }}
                </p>
                <p v-if="campaign.attachmentsPrunedAt" :class="ui.muted">
                    {{
                        $t('mailing::campaigns.show.attachments_pruned', {
                            time: campaign.attachmentsPrunedAt,
                        })
                    }}
                </p>
                <dl class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <div
                        v-for="count in counts"
                        :key="count.status"
                        class="flex flex-col"
                    >
                        <dt :class="ui.muted">{{ count.label }}</dt>
                        <dd class="text-lg font-semibold">{{ count.count }}</dd>
                    </div>
                </dl>
            </section>

            <section v-if="attachments.length > 0" :class="ui.panel">
                <h2 :class="ui.heading">
                    {{ $t('mailing::campaigns.show.attachments') }}
                </h2>
                <ul class="flex flex-col gap-2">
                    <li
                        v-for="attachment in attachments"
                        :key="attachment.id"
                        class="flex flex-wrap items-baseline gap-x-3 text-sm"
                    >
                        <span class="font-medium break-all">{{
                            attachment.name
                        }}</span>
                        <span :class="ui.muted">{{ attachment.size }}</span>
                        <span :class="ui.muted">{{ attachment.delivery }}</span>
                    </li>
                </ul>
            </section>

            <section v-if="preflight.length > 0" :class="ui.panel">
                <h2 :class="ui.heading">
                    {{ $t('mailing::campaigns.show.preflight') }}
                </h2>
                <p :class="ui.muted">
                    {{ $t('mailing::campaigns.show.preflight_description') }}
                </p>
                <ul class="flex flex-col gap-2">
                    <li
                        v-for="item in preflight"
                        :key="item.key"
                        class="flex items-start gap-3"
                    >
                        <span
                            class="inline-flex min-w-24 justify-center rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="statusClass[item.status]"
                        >
                            {{ item.label }}
                        </span>
                        <span class="text-sm break-all">{{
                            item.message
                        }}</span>
                    </li>
                </ul>
            </section>

            <section
                v-if="scheduleForm || scheduleBlockedReason"
                :class="ui.panel"
            >
                <h2 :class="ui.heading">
                    {{ $t('mailing::campaigns.show.schedule') }}
                </h2>
                <p v-if="scheduleBlockedReason" :class="ui.notice">
                    {{ scheduleBlockedReason }}
                </p>
                <Form v-if="scheduleForm" :schema="scheduleForm" />
            </section>

            <section v-if="testForm" :class="ui.panel">
                <h2 :class="ui.heading">
                    {{ $t('mailing::campaigns.show.test') }}
                </h2>
                <Form :schema="testForm" />
            </section>

            <section v-if="sends" class="flex flex-col gap-3">
                <h2 :class="ui.heading">
                    {{ $t('mailing::campaigns.show.sends') }}
                </h2>
                <TableBuilder :table="sends" :confirm="confirm" />
            </section>
        </div>
    </MailingLayout>
</template>
