const base =
    'inline-flex items-center justify-center gap-1.5 rounded-md border px-3 py-1.5 text-sm font-medium shadow-sm transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-50';

export const ui = {
    button: `${base} border-gray-300 bg-white text-gray-800 hover:bg-gray-50 focus-visible:ring-gray-400 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700`,
    primaryButton: `${base} border-transparent bg-gray-900 text-white hover:bg-gray-700 focus-visible:ring-gray-500 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-gray-300`,
    dangerButton: `${base} border-red-300 bg-white text-red-700 hover:bg-red-50 focus-visible:ring-red-400 dark:border-red-800 dark:bg-gray-800 dark:text-red-300 dark:hover:bg-red-950`,
    panel: 'flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-4 text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100',
    heading: 'text-base font-semibold',
    title: 'text-xl font-semibold tracking-tight',
    toolbar: 'flex flex-wrap items-center gap-2',
    muted: 'text-sm text-gray-500 dark:text-gray-400',
    badge: 'inline-flex items-center rounded-full border border-gray-300 px-2 py-0.5 text-xs font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200',
    publishedBadge:
        'inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-950 dark:text-green-200',
    error: 'rounded-md bg-red-50 px-3 py-2 text-sm text-red-800 dark:bg-red-950 dark:text-red-200',
    notice: 'rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-100',
} as const;
