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

export type MailingAction = {
    label: string;
    url: string;
    method: 'get' | 'post' | 'delete';
    confirm: string | null;
    disabledReason: string | null;
    variant: 'default' | 'destructive';
};

export type MailingAttachment = {
    id: number;
    name: string;
    size: string;
    delivery: string;
};

export type MailingPreflightItem = {
    key: string;
    status: 'ok' | 'warning' | 'error';
    label: string;
    message: string;
};

export type MailingCampaign = {
    subject: string;
    list: string;
    status: string;
    statusLabel: string;
    scheduledAt: string | null;
    startedAt: string | null;
    finishedAt: string | null;
    attachmentsPrunedAt: string | null;
    recipients: number;
    estimatedFinish: string | null;
    transport: string;
};

export type MailingNavigationItem = {
    key: string;
    title: string;
    href: string;
    active: boolean;
};
