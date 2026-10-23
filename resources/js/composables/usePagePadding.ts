import { inject } from 'vue';

export const PAGE_PADDING = 'pagePadding';

export function usePagePadding(): boolean {
    return inject<boolean>(PAGE_PADDING, true);
}
