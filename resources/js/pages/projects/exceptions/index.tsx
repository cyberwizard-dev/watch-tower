import { router } from '@inertiajs/react';

import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { AppLayout } from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types/pagination';

type ExceptionRow = {
    id: string;
    fingerprint: string;
    exception_class: string;
    first_message: string;
    first_file: string | null;
    first_line: number | null;
    total_count: number;
    first_occurrence_at: string | null;
    last_occurrence_at: string | null;
    status: string;
};

type Props = {
    groups: Paginated<ExceptionRow>;
    filters: { status: string };
};

const STATUS_TABS = [
    { label: 'Unresolved', value: 'unresolved' },
    { label: 'Resolved', value: 'resolved' },
    { label: 'Ignored', value: 'ignored' },
    { label: 'All', value: 'all' },
];

export default function ExceptionsIndex({ groups, filters }: Props) {
    const onFilter = (value: string) => {
        const url = new URL(window.location.href);
        url.searchParams.set('status', value);
        router.visit(url.pathname + url.search, { preserveScroll: true });
    };

    return (
        <AppLayout title="Exceptions">
            <PageHeader
                title="Exceptions"
                breadcrumbs={[{ label: 'Activity' }, { label: 'Exceptions' }]}
                actions={
                    <div className="inline-flex items-center rounded-md border border-[#e6e7eb] bg-white p-0.5 text-xs shadow-sm dark:border-[#1d2129] dark:bg-[#0f1217]">
                        {STATUS_TABS.map((tab) => {
                            const active = tab.value === filters.status;
                            return (
                                <button
                                    key={tab.label}
                                    type="button"
                                    onClick={() => onFilter(tab.value)}
                                    className={cn(
                                        'rounded px-2 py-1 transition-colors',
                                        active
                                            ? 'bg-[#1f2330] text-white shadow dark:bg-emerald-500'
                                            : 'text-[#5e6470] hover:text-[#1f2330] dark:text-[#a0a6b1] dark:hover:text-white',
                                    )}
                                >
                                    {tab.label}
                                </button>
                            );
                        })}
                    </div>
                }
            />

            <div className="px-6 py-6">
                <div className="overflow-hidden rounded-lg border border-[#e6e7eb] bg-white shadow-sm dark:border-[#1d2129] dark:bg-[#0f1217]">
                    {groups.data.length === 0 ? (
                        <div className="flex flex-col items-center justify-center px-6 py-16 text-center">
                            <span className="text-3xl">🎉</span>
                            <p className="mt-2 text-sm font-medium">No exceptions match the filter</p>
                            <p className="text-xs text-[#9aa0aa]">Try widening the time range or status</p>
                        </div>
                    ) : (
                        <ul>
                            {groups.data.map((group) => (
                                <li
                                    key={group.id}
                                    className="border-b border-[#f1f3f7] px-5 py-4 last:border-b-0 hover:bg-[#fafbfc] dark:border-[#1d2129] dark:hover:bg-[#13171e]"
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2 text-xs">
                                                <span className="rounded bg-rose-100 px-1.5 py-0.5 font-mono font-semibold text-rose-700 dark:bg-rose-500/15 dark:text-rose-300">
                                                    {shortClass(group.exception_class)}
                                                </span>
                                                <span className="text-[#9aa0aa]">{group.first_file ?? 'unknown'}{group.first_line ? `:${group.first_line}` : ''}</span>
                                            </div>
                                            <p className="mt-1 truncate text-sm font-medium">{group.first_message}</p>
                                            <p className="mt-1 text-xs text-[#9aa0aa]">
                                                {group.total_count.toLocaleString()} occurrence{group.total_count === 1 ? '' : 's'}
                                                {' · last seen '}{formatRelative(group.last_occurrence_at)}
                                            </p>
                                        </div>
                                        <span
                                            className={cn(
                                                'rounded px-2 py-0.5 text-[11px] font-medium',
                                                group.status === 'resolved'
                                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
                                                    : group.status === 'ignored'
                                                        ? 'bg-zinc-200 text-zinc-700 dark:bg-zinc-700/30 dark:text-zinc-300'
                                                        : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
                                            )}
                                        >
                                            {group.status}
                                        </span>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}

                    <Pagination links={groups.links} from={groups.from} to={groups.to} total={groups.total} />
                </div>
            </div>
        </AppLayout>
    );
}

function shortClass(fqcn: string): string {
    const parts = fqcn.split('\\');
    return parts[parts.length - 1] ?? fqcn;
}

function formatRelative(iso: string | null): string {
    if (!iso) return 'never';
    const date = new Date(iso);
    const diffMs = Date.now() - date.getTime();
    const seconds = Math.floor(diffMs / 1000);
    if (seconds < 60) return `${seconds}s ago`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    return `${Math.floor(seconds / 86400)}d ago`;
}
