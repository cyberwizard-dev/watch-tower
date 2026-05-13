import { Link, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ArrowUpDown, CalendarClock, ExternalLink, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Scatter,
    ScatterChart,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

import { PageHeader } from '@/components/page-header';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { AppLayout } from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import scheduledTasksRoutes from '@/routes/projects/scheduled-tasks';

type SummaryBucket = {
    bucket: string;
    processed: number;
    skipped: number;
    failed: number;
    avg_duration: number | null;
    p95_duration: number | null;
};

type Summary = {
    totals: { total: number; processed: number; skipped: number; failed: number };
    duration: {
        min_ms: number | null;
        max_ms: number | null;
        avg_ms: number | null;
        p95_ms: number | null;
        threshold_ms: number | null;
    };
    buckets: SummaryBucket[];
};

type TaskRow = {
    task: string;
    task_hash: string;
    schedule: string | null;
    next_run_at: string | null;
    processed: number;
    skipped: number;
    failed: number;
    total: number;
    avg_ms: number | null;
    p95_ms: number | null;
};

type Props = {
    summary: Summary;
    tasks: TaskRow[];
    selectedRange: string;
    filters: { search: string | null };
};

type SortKey = 'task' | 'processed' | 'skipped' | 'failed' | 'total' | 'avg_ms' | 'p95_ms';
type SortDir = 'asc' | 'desc';

export default function ScheduledTasksIndex({ summary, tasks, selectedRange, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [sortKey, setSortKey] = useState<SortKey>('task');
    const [sortDir, setSortDir] = useState<SortDir>('asc');

    const sortedTasks = useMemo(() => {
        const copy = [...tasks];
        copy.sort((a, b) => {
            const av = a[sortKey];
            const bv = b[sortKey];
            if (av === null && bv === null) return 0;
            if (av === null) return 1;
            if (bv === null) return -1;
            if (typeof av === 'number' && typeof bv === 'number') {
                return sortDir === 'asc' ? av - bv : bv - av;
            }
            return sortDir === 'asc' ? String(av).localeCompare(String(bv)) : String(bv).localeCompare(String(av));
        });
        return copy;
    }, [tasks, sortKey, sortDir]);

    const toggleSort = (key: SortKey) => {
        if (sortKey === key) {
            setSortDir(sortDir === 'asc' ? 'desc' : 'asc');
        } else {
            setSortKey(key);
            setSortDir(key === 'task' ? 'asc' : 'desc');
        }
    };

    const onSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const url = new URL(window.location.href);
        if (search) {
            url.searchParams.set('search', search);
        } else {
            url.searchParams.delete('search');
        }
        router.visit(url.pathname + url.search, { preserveScroll: true, preserveState: true });
    };

    return (
        <AppLayout title="Scheduled Tasks">
            <PageHeader
                title="Scheduled Tasks"
                breadcrumbs={[{ label: 'Activity' }, { label: 'Scheduled Tasks' }]}
                selectedRange={selectedRange}
            />

            <div className="space-y-6 px-6 py-6">
                <div className="grid gap-6 lg:grid-cols-2">
                    <ScheduledTasksCard summary={summary} />
                    <DurationCard summary={summary} />
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-3">
                        <CardTitle>{sortedTasks.length} Tasks</CardTitle>
                        <form onSubmit={onSearchSubmit} className="relative w-64">
                            <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search tasks"
                                className="h-8 pl-8 text-xs"
                            />
                        </form>
                    </CardHeader>
                    <Separator />
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <SortableHead label="TASK" sortKey="task" current={sortKey} dir={sortDir} onSort={toggleSort} />
                                    <TableHead className="w-44">SCHEDULE</TableHead>
                                    <TableHead className="w-32">NEXT RUN</TableHead>
                                    <SortableHead label="PROCESSED" sortKey="processed" current={sortKey} dir={sortDir} onSort={toggleSort} align="right" className="w-28" />
                                    <SortableHead label="SKIPPED" sortKey="skipped" current={sortKey} dir={sortDir} onSort={toggleSort} align="right" className="w-24" />
                                    <SortableHead label="FAILED" sortKey="failed" current={sortKey} dir={sortDir} onSort={toggleSort} align="right" className="w-24" />
                                    <SortableHead label="TOTAL" sortKey="total" current={sortKey} dir={sortDir} onSort={toggleSort} align="right" className="w-24" />
                                    <SortableHead label="AVG" sortKey="avg_ms" current={sortKey} dir={sortDir} onSort={toggleSort} align="right" className="w-24" />
                                    <SortableHead label="P95" sortKey="p95_ms" current={sortKey} dir={sortDir} onSort={toggleSort} align="right" className="w-24" />
                                    <TableHead className="w-10" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {sortedTasks.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={10} className="py-10 text-center text-sm text-muted-foreground">
                                            No scheduled tasks captured in {selectedRange.toUpperCase()}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    sortedTasks.map((row) => <TaskRowTr key={row.task_hash} row={row} />)
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function TaskRowTr({ row }: { row: TaskRow }) {
    const slug = (typeof window !== 'undefined' ? window.location.pathname.split('/')[2] : '') || '';
    const href = scheduledTasksRoutes.show({ project: slug, task: row.task_hash }).url;

    return (
        <TableRow className="cursor-pointer hover:bg-muted/50">
            <TableCell className="py-2.5">
                <Link href={href} className="flex items-center gap-2 font-mono text-xs hover:text-foreground">
                    <span className="grid h-5 w-5 place-items-center rounded bg-muted text-muted-foreground">
                        <CalendarClock className="h-3 w-3" />
                    </span>
                    {row.task}
                </Link>
            </TableCell>
            <TableCell className="py-2.5 text-xs text-muted-foreground">{row.schedule ?? '—'}</TableCell>
            <TableCell className="py-2.5 font-mono text-xs text-muted-foreground">{formatCountdown(row.next_run_at)}</TableCell>
            <Numeric value={row.processed} muted />
            <Numeric value={row.skipped} muted />
            <Numeric value={row.failed} muted />
            <Numeric value={row.total} />
            <Numeric value={formatMs(row.avg_ms)} muted raw />
            <Numeric value={formatMs(row.p95_ms)} muted raw />
            <TableCell className="py-2.5 text-right">
                <Link href={href} className="inline-flex text-muted-foreground hover:text-foreground">
                    <ExternalLink className="h-3.5 w-3.5" />
                </Link>
            </TableCell>
        </TableRow>
    );
}

function Numeric({ value, muted, raw }: { value: number | string; muted?: boolean; raw?: boolean }) {
    const text = raw ? String(value) : typeof value === 'number' ? formatNumber(value) : value;
    return <TableCell className={cn('py-2.5 text-right font-mono text-xs', muted && 'text-muted-foreground')}>{text}</TableCell>;
}

function ScheduledTasksCard({ summary }: { summary: Summary }) {
    const { totals, buckets } = summary;
    const data = buckets.map((b) => ({
        time: formatTime(b.bucket),
        Processed: b.processed,
        Skipped: b.skipped,
        Failed: b.failed,
    }));

    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="text-xs uppercase tracking-wider text-muted-foreground">Scheduled Tasks</CardTitle>
            </CardHeader>
            <Separator />
            <CardContent className="p-5">
                <div className="flex items-baseline gap-6">
                    <div>
                        <div className="text-3xl font-semibold">{formatNumber(totals.total)}</div>
                        <div className="text-[10px] uppercase tracking-wider text-muted-foreground">Tasks</div>
                    </div>
                    <Separator orientation="vertical" className="h-10" />
                    <Stat label="Failed" value={formatNumber(totals.failed)} accent="text-rose-600 dark:text-rose-400" dot="bg-rose-500" />
                    <Stat label="Processed" value={formatNumber(totals.processed)} accent="text-emerald-600 dark:text-emerald-400" dot="bg-emerald-500" />
                    <Stat label="Skipped" value={formatNumber(totals.skipped)} accent="text-amber-600 dark:text-amber-400" dot="bg-amber-500" />
                </div>

                <div className="mt-5 h-40">
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={data}>
                            <CartesianGrid stroke="var(--color-border)" strokeDasharray="3 3" vertical={false} />
                            <XAxis dataKey="time" tick={{ fontSize: 10, fill: 'var(--color-muted-foreground)' }} interval={Math.max(0, Math.floor(data.length / 6))} />
                            <YAxis tick={{ fontSize: 10, fill: 'var(--color-muted-foreground)' }} width={28} />
                            <Tooltip cursor={{ fill: 'var(--color-muted)' }} contentStyle={tooltipStyle} />
                            <Bar dataKey="Processed" stackId="s" fill="#10b981" />
                            <Bar dataKey="Skipped" stackId="s" fill="#f59e0b" />
                            <Bar dataKey="Failed" stackId="s" fill="#ef4444" radius={[2, 2, 0, 0]} />
                        </BarChart>
                    </ResponsiveContainer>
                </div>

                <div className="mt-3 flex items-center justify-between text-[10px] text-muted-foreground">
                    <span>{buckets[0] ? formatUtc(buckets[0].bucket) : ''}</span>
                    <span>{buckets[buckets.length - 1] ? formatUtc(buckets[buckets.length - 1].bucket) : ''}</span>
                </div>
            </CardContent>
        </Card>
    );
}

function DurationCard({ summary }: { summary: Summary }) {
    const { duration, buckets } = summary;
    const avgPoints = buckets.flatMap((b, i) => (b.avg_duration === null ? [] : [{ x: i, y: b.avg_duration, time: b.bucket }]));
    const p95Points = buckets.flatMap((b, i) => (b.p95_duration === null ? [] : [{ x: i, y: b.p95_duration, time: b.bucket }]));

    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="text-xs uppercase tracking-wider text-muted-foreground">Duration</CardTitle>
            </CardHeader>
            <Separator />
            <CardContent className="p-5">
                <div className="flex items-baseline gap-6">
                    <div>
                        <div className="text-3xl font-semibold">
                            {formatMs(duration.min_ms)}
                            <span className="px-2 text-base text-muted-foreground">–</span>
                            {formatMs(duration.max_ms)}
                        </div>
                        <div className="text-[10px] uppercase tracking-wider text-muted-foreground">Min – Max</div>
                    </div>
                    <Separator orientation="vertical" className="h-10" />
                    <Stat label="Avg" value={formatMs(duration.avg_ms)} accent="text-foreground" dot="bg-muted-foreground" />
                    <Stat label="P95" value={formatMs(duration.p95_ms)} accent="text-amber-600 dark:text-amber-400" dot="bg-amber-500" />
                </div>

                <div className="mt-5 h-40">
                    <ResponsiveContainer width="100%" height="100%">
                        <ScatterChart>
                            <CartesianGrid stroke="var(--color-border)" strokeDasharray="3 3" vertical={false} />
                            <XAxis dataKey="x" type="number" domain={[0, buckets.length - 1]} tick={false} axisLine={false} />
                            <YAxis dataKey="y" type="number" tick={{ fontSize: 10, fill: 'var(--color-muted-foreground)' }} width={32} unit="ms" />
                            <Tooltip contentStyle={tooltipStyle} formatter={(value) => `${Number(value).toFixed(0)} ms`} cursor={{ strokeDasharray: '3 3' }} />
                            <Scatter name="Avg" data={avgPoints} fill="#9ca3af" />
                            <Scatter name="P95" data={p95Points} fill="#f59e0b" />
                        </ScatterChart>
                    </ResponsiveContainer>
                </div>

                <div className="mt-3 flex items-center justify-between text-[10px] text-muted-foreground">
                    <span>{buckets[0] ? formatUtc(buckets[0].bucket) : ''}</span>
                    <span>{buckets[buckets.length - 1] ? formatUtc(buckets[buckets.length - 1].bucket) : ''}</span>
                </div>
            </CardContent>
        </Card>
    );
}

function SortableHead({
    label,
    sortKey,
    current,
    dir,
    onSort,
    align,
    className,
}: {
    label: string;
    sortKey: SortKey;
    current: SortKey;
    dir: SortDir;
    onSort: (key: SortKey) => void;
    align?: 'right';
    className?: string;
}) {
    const isActive = current === sortKey;
    const Icon = !isActive ? ArrowUpDown : dir === 'asc' ? ArrowUp : ArrowDown;
    return (
        <TableHead className={cn(align === 'right' && 'text-right', className)}>
            <button
                type="button"
                onClick={() => onSort(sortKey)}
                className={cn(
                    'inline-flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wider',
                    align === 'right' && 'flex-row-reverse',
                    isActive ? 'text-foreground' : 'text-muted-foreground hover:text-foreground',
                )}
            >
                {label}
                <Icon className="h-3 w-3" />
            </button>
        </TableHead>
    );
}

function Stat({ label, value, accent, dot }: { label: string; value: string; accent: string; dot?: string }) {
    return (
        <div>
            <div className="flex items-center gap-1.5 text-[10px] uppercase tracking-wider text-muted-foreground">
                {dot ? <span className={cn('h-1.5 w-1.5 rounded-full', dot)} /> : null}
                {label}
            </div>
            <div className={cn('text-base font-semibold', accent)}>{value}</div>
        </div>
    );
}

function formatNumber(value: number): string {
    return new Intl.NumberFormat().format(value);
}

function formatMs(value: number | null): string {
    if (value === null || Number.isNaN(value)) {
        return '—';
    }
    if (value >= 1000) {
        return `${(value / 1000).toFixed(2)} s`;
    }
    return `${Math.round(value)} ms`;
}

function formatTime(iso: string): string {
    return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function formatUtc(iso: string | null): string {
    if (!iso) return '—';
    const d = new Date(iso);
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())} ${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}:${pad(d.getUTCSeconds())} UTC`;
}

function formatCountdown(iso: string | null): string {
    if (!iso) return '—';
    const target = new Date(iso).getTime();
    const diff = target - Date.now();
    if (diff <= 0) return 'soon';
    const totalSeconds = Math.floor(diff / 1000);
    const days = Math.floor(totalSeconds / 86400);
    const hours = Math.floor((totalSeconds % 86400) / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    if (days > 0) return `${days}d ${hours}h`;
    if (hours > 0) return `${hours}h ${minutes}m`;
    return `${minutes}m ${seconds}s`;
}

const tooltipStyle = {
    background: 'var(--color-popover)',
    border: '1px solid var(--color-border)',
    borderRadius: 6,
    color: 'var(--color-popover-foreground)',
    fontSize: 12,
} as const;
