import { setLayoutProps } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import type { MailingBreadcrumb } from '@mailing/types';

export function useBreadcrumbs(breadcrumbs: () => MailingBreadcrumb[]): void {
    watchEffect(() => {
        setLayoutProps({
            breadcrumbs: breadcrumbs(),
        });
    });
}
