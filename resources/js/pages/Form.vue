<script setup lang="ts">
import Form from '@form-builder/components/Form.vue';
import type { FormSchema } from '@form-builder/types/form-builder';
import { Head, Link } from '@inertiajs/vue3';
import { useBreadcrumbs } from '@mailing/composables/useBreadcrumbs';
import type { MailingBreadcrumb } from '@mailing/types';
import { ui } from '@mailing/ui';

const props = defineProps<{
    title: string;
    description?: string | null;
    breadcrumbs: MailingBreadcrumb[];
    backUrl?: string | null;
    form: FormSchema;
}>();

useBreadcrumbs(() => props.breadcrumbs);
</script>

<template>
    <Head :title="title" />

    <div class="flex max-w-3xl flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 :class="ui.title">{{ title }}</h1>
                <p v-if="description" :class="ui.muted">{{ description }}</p>
            </div>

            <Link v-if="backUrl" :href="backUrl" :class="ui.button">
                {{ $t('mailing::common.back') }}
            </Link>
        </div>

        <section :class="ui.panel">
            <Form :schema="form" />
        </section>
    </div>
</template>
