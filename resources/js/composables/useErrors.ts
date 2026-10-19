import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';

export function useErrors(keys: string[]): ComputedRef<string[]> {
    const page = usePage();

    return computed(() => {
        const errors = (page.props.errors ?? {}) as Record<string, string>;

        return keys
            .map((key) => errors[key])
            .filter(
                (message): message is string => typeof message === 'string',
            );
    });
}
