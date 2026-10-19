import { inject } from 'vue';

export type MailingConfirm = (message: string) => Promise<boolean>;

const decline: MailingConfirm = () => Promise.resolve(false);

export function useConfirm(): MailingConfirm {
    return inject<MailingConfirm>('confirm', decline);
}
