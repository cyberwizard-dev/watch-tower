import { router } from '@inertiajs/react';

import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { AppLayout } from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types/pagination';

type TraceRow = {
    id: string;
    correlation_id: string;
    method: string;
    uri: string;
    status_code: number | null;
    duration_ms: number | null;
    db_queries_count: number;
    has_errors: boolean;
    environment: string | null;
    occurred_at: string | null;
};

type Props = {
    traces: Paginated<TraceRow>;
    filters: { status: string | null };
};

const STATUS_TABS: { label: string; value: string | null }[] = [
    { label: 'All', value: null },
    { label: 'Success', value: 'success' },
    { label: 'Error', value: 'error' },
];

export default function RequestsIndex({ traces, filters }: Props) {
    const onFilter = (value: string | null) => {
        const url = new URL(window.location.href);
        if (value) {
            url.searchParams.set('status', value);
        } else {
            url.searchParams.delete('status');
        }
        router.visit(url.pathname + url.search, { preserveScroll: true });
    };

    return (
        <AppLayout title="Requests">
            <PageHeader
                title="Requests"
                breadcrumbs={[{ label: 'Activity' }, { label: 'Requests' }]}
                selectedRange="1h"
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
                    <table className="w-full text-sm">
                        <thead className="border-b border-[#e6e7eb] bg-[#fafbfc] text-left text-[11px] uppercase tracking-wider text-[#9aa0aa] dark:border-[#1d2129] dark:bg-[#0e1116]">
                            <tr>
                                <th className="px-5 py-2 font-medium">Method</th>
                                <th className="px-5 py-2 font-medium">URI</th>
                                <th className="px-5 py-2 font-medium">Status</th>
                                <th className="px-5 py-2 font-medium text-right">Duration</th>
                                <th className="px-5 py-2 font-medium text-right">Queries</th>
                                <th className="px-5 py-2 font-medium">When</th>
                            </tr>
                        </thead>
                        <tbody>
                            {traces.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-5 py-10 text-center text-[#9aa0aa]">
                                        No requests captured yet
                                    </td>
                                </tr>
                            ) : (
                                traces.data.map((trace) => (
                                    <tr key={trace.id} className="border-t border-[#f1f3f7] hover:bg-[#fafbfc] dark:border-[#1d2129] dark:hover:bg-[#13171e]">
                                        <td className="px-5 py-2 font-mono text-xs">
                                            <span className="rounded bg-[#eef1f6] px-1.5 py-0.5 dark:bg-[#1a1f29]">{trace.method}</span>
                                        </td>
                                        <td className="px-5 py-2 font-mono text-xs">{trace.uri}</td>
                                        <td className="px-5 py-2">
                                            <StatusPill status={trace.status_code} />
                                        </td>
                                        <td className="px-5 py-2 text-right font-mono text-xs">
                                            {trace.duration_ms !== null ? `${trace.duration_ms} ms` : '—'}
                                        </td>
                                        <td className="px-5 py-2 text-right font-mono text-xs">{trace.db_queries_count}</td>
                                        <td className="px-5 py-2 text-xs text-[#5e6470] dark:text-[#a0a6b1]">{formatRelative(trace.occurred_at)}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>

                    <Pagination links={traces.links} from={traces.from} to={traces.to} total={traces.total} />
                </div>
            </div>
        </AppLayout>
    );
}

function StatusPill({ status }: { status: number | null }) {
    if (status === null) {
        return <span className="text-xs text-[#9aa0aa]">—</span>;
    }

    let color = 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300';
    if (status >= 500) color = 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300';
    else if (status >= 400) color = 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300';
    else if (status >= 300) color = 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300';

    return <span className={`rounded px-1.5 py-0.5 font-mono text-xs ${color}`}>{status}</span>;
}

function formatRelative(iso: string | null): string {
    if (!iso) return '—';
    const date = new Date(iso);
    const diffMs = Date.now() - date.getTime();
    const seconds = Math.floor(diffMs / 1000);
    if (seconds < 60) return `${seconds}s ago`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    return `${Math.floor(seconds / 86400)}d ago`;
}
