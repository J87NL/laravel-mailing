import * as inertia from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import type { MailingBreadcrumb } from '@mailing/types';

type SetLayoutProps = (props: Record<string, unknown>) => void;

const setLayoutProps = (inertia as unknown as { setLayoutProps?: SetLayoutProps }).setLayoutProps;

export function useBreadcrumbs(breadcrumbs: () => MailingBreadcrumb[]): void {
    if (typeof setLayoutProps !== 'function') {
        return;
    }

    watchEffect(() => {
        setLayoutProps({
            breadcrumbs: breadcrumbs(),
        });
    });
}
