export type MailingBreadcrumb = {
    title: string;
    href: string;
};

export type MailingHistoryEntry = {
    id: number;
    action: string;
    list: string | null;
    source: string | null;
    ip: string | null;
    note: string | null;
    at: string | null;
};

export type MailingSubscriber = {
    email: string;
    name: string | null;
    createdAt: string | null;
    suppression: {
        reason: string;
        createdAt: string | null;
    } | null;
};
