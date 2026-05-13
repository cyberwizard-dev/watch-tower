import { Link, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ArrowUpDown, ExternalLink, Search, Users } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Bar, BarChart, CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

import { PageHeader } from '@/components/page-header';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { AppLayout } from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import jobsRoutes from '@/routes/projects/jobs';

type SummaryBucket = {
    bucket: string;
    processed: number;
    released: number;
    failed: number;
    avg_duration: number | null;
    p95_duration: number | null;
};

type Summary = {
    totals: { total: number; queued: number; processed: number; released: number; failed: number };
    duration: {
        min_ms: number | null;
        max_ms: number | null;
        avg_ms: number | null;
        p95_ms: number | null;
        threshold_ms: number | null;
    };
    buckets: SummaryBucket[];
};

type JobRow = {
    job_class: string;
    queued: number;
    processed: number;
    released: number;
    failed: number;
    total: number;
    avg_ms: number | null;
    p95_ms: number | null;
};

type Props = {
    summary: Summary;
    jobs: JobRow[];
    selectedRange: string;
    filters: { search: string | null };
};

type SortKey = 'job_class' | 'queued' | 'processed' | 'released' | 'failed' | 'total' | 'avg_ms' | 'p95_ms';
type SortDir = 'asc' | 'desc';

export default function JobsIndex({ summary, jobs, selectedRange, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [sortKey, setSortKey] = useState<SortKey>('job_class');
    const [sortDir, setSortDir] = useState<SortDir>('asc');

    const sortedJobs = useMemo(() => {
        const copy = [...jobs];
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
    }, [jobs, sortKey, sortDir]);

    const toggleSort = (key: SortKey) => {
        if (sortKey === key) {
            setSortDir(sortDir === 'asc' ? 'desc' : 'asc');
        } else {
            setSortKey(key);
            setSortDir(key === 'job_class' ? 'asc' : 'desc');
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
        <AppLayout title="Jobs">
            <PageHeader
                title="Jobs"
                breadcrumbs={[{ label: 'Activity' }, { label: 'Jobs' }]}
                selectedRange={selectedRange}
                actions={
                    <Select value="__all__" onValueChange={() => undefined}>
                        <SelectTrigger className="h-8 w-40 text-xs">
                            <div className="flex items-center gap-1.5">
                                <Users className="h-3.5 w-3.5" />
                                <SelectValue placeholder="All Users" />
                            </div>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__all__">All Users</SelectItem>
                        </SelectContent>
                    </Select>
                }
            />

            <div className="space-y-6 px-6 py-6">
                <div className="grid gap-6 lg:grid-cols-2">
                    <AttemptsCard summary={summary} />
                    <DurationCard summary={summary} />
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-3">
                        <CardTitle>{sortedJobs.length} Jobs</CardTitle>
                        <form onSubmit={onSearchSubmit} className="relative w-64">
                            <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search jobs"
                                className="h-8 pl-8 text-xs"
                            />
                        </form>
                    </CardHeader>
                    <Separator />
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <SortableHead label="JOBS" sortKey="job_class" current={sortKey} dir={sortDir} onSort={toggleSort} />
                                    <SortableHead label="QUEUED" sortKey="queued" current={sortKey} dir={sortDir} onSort={toggleSort} align="right" className="w-24" />
                                    <SortableHead label="PROCESSED" sortKey="processed" current={sortKey} dir={sortDir} onSort={toggleSort} align="right" className="w-28" />
                                    <SortableHead label="RELEASED" sortKey="released" current={sortKey} dir={sortDir} onSort={toggleSort} align="right" className="w-24" />
                                    <SortableHead label="FAILED" sortKey="failed" current={sortKey} dir={sortDir} onSort={toggleSort} align="right" className="w-24" />
                                    <SortableHead label="TOTAL" sortKey="total" current={sortKey} dir={sortDir} onSort={toggleSort} align="right" className="w-24" />
                                    <SortableHead label="AVG" sortKey="avg_ms" current={sortKey} dir={sortDir} onSort={toggleSort} align="right" className="w-24" />
                                    <SortableHead label="P95" sortKey="p95_ms" current={sortKey} dir={sortDir} onSort={toggleSort} align="right" className="w-24" />
                                    <TableHead className="w-10" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {sortedJobs.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={9} className="py-10 text-center text-sm text-muted-foreground">
                                            No jobs captured in {selectedRange.toUpperCase()}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    sortedJobs.map((row) => (
                                        <JobRowTr key={row.job_class} row={row} />
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function JobRowTr({ row }: { row: JobRow }) {
    const slug = (typeof window !== 'undefined' ? window.location.pathname.split('/')[2] : '') || '';
    const href = jobsRoutes.show({ project: slug, job: row.job_class.replace(/\\/g, '--') }).url;

    return (
        <TableRow className="cursor-pointer hover:bg-muted/50">
            <TableCell className="py-2.5">
                <Link href={href} className="flex items-center gap-2 font-mono text-xs hover:text-foreground">
                    <span className="grid h-5 w-5 place-items-center rounded bg-muted text-[10px] text-muted-foreground">⊙</span>
                    {row.job_class}
                </Link>
            </TableCell>
            <Numeric value={row.queued} muted />
            <Numeric value={row.processed} muted />
            <Numeric value={row.released} muted />
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
    return (
        <TableCell className={cn('py-2.5 text-right font-mono text-xs', muted && 'text-muted-foreground')}>{text}</TableCell>
    );
}

function AttemptsCard({ summary }: { summary: Summary }) {
    const { totals, buckets } = summary;
    const data = buckets.map((b) => ({
        time: formatTime(b.bucket),
        Processed: b.processed,
        Released: b.released,
        Failed: b.failed,
    }));

    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="text-xs uppercase tracking-wider text-muted-foreground">Attempts</CardTitle>
            </CardHeader>
            <Separator />
            <CardContent className="p-5">
                <div className="flex items-baseline gap-6">
                    <div>
                        <div className="text-3xl font-semibold">{formatNumber(totals.total)}</div>
                        <div className="text-[10px] uppercase tracking-wider text-muted-foreground">Total</div>
                    </div>
                    <Separator orientation="vertical" className="h-10" />
                    <Stat label="Failed" value={formatNumber(totals.failed)} accent="text-rose-600 dark:text-rose-400" />
                    <Stat label="Processed" value={formatNumber(totals.processed)} accent="text-emerald-600 dark:text-emerald-400" />
                    <Stat label="Released" value={formatNumber(totals.released)} accent="text-amber-600 dark:text-amber-400" />
                </div>

                <div className="mt-5 h-40">
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={data}>
                            <CartesianGrid stroke="var(--color-border)" strokeDasharray="3 3" vertical={false} />
                            <XAxis dataKey="time" tick={{ fontSize: 10, fill: 'var(--color-muted-foreground)' }} interval={Math.max(0, Math.floor(data.length / 6))} />
                            <YAxis tick={{ fontSize: 10, fill: 'var(--color-muted-foreground)' }} width={28} />
                            <Tooltip cursor={{ fill: 'var(--color-muted)' }} contentStyle={tooltipStyle} />
                            <Bar dataKey="Processed" stackId="s" fill="#10b981" />
                            <Bar dataKey="Released" stackId="s" fill="#f59e0b" />
                            <Bar dataKey="Failed" stackId="s" fill="#ef4444" radius={[2, 2, 0, 0]} />
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            </CardContent>
        </Card>
    );
}

function DurationCard({ summary }: { summary: Summary }) {
    const { duration, buckets } = summary;
    const data = buckets.map((b) => ({
        time: formatTime(b.bucket),
        avg: b.avg_duration ?? 0,
        p95: b.p95_duration ?? 0,
    }));

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
                    <Stat label="Avg" value={formatMs(duration.avg_ms)} accent="text-indigo-600 dark:text-indigo-400" />
                    <Stat label="P95" value={formatMs(duration.p95_ms)} accent="text-violet-600 dark:text-violet-400" />
                </div>

                <div className="mt-5 h-40">
                    <ResponsiveContainer width="100%" height="100%">
                        <LineChart data={data}>
                            <CartesianGrid stroke="var(--color-border)" strokeDasharray="3 3" vertical={false} />
                            <XAxis dataKey="time" tick={{ fontSize: 10, fill: 'var(--color-muted-foreground)' }} interval={Math.max(0, Math.floor(data.length / 6))} />
                            <YAxis tick={{ fontSize: 10, fill: 'var(--color-muted-foreground)' }} width={28} unit="ms" />
                            <Tooltip contentStyle={tooltipStyle} formatter={(value) => `${Number(value).toFixed(0)} ms`} />
                            <Line type="monotone" dataKey="avg" stroke="#6366f1" strokeWidth={2} dot={{ r: 2, fill: '#6366f1' }} activeDot={{ r: 4 }} />
                            <Line type="monotone" dataKey="p95" stroke="#8b5cf6" strokeWidth={2} strokeDasharray="4 2" dot={false} />
                        </LineChart>
                    </ResponsiveContainer>
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

function Stat({ label, value, accent }: { label: string; value: string; accent: string }) {
    return (
        <div>
            <div className="text-[10px] uppercase tracking-wider text-muted-foreground">{label}</div>
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

const tooltipStyle = {
    background: 'var(--color-popover)',
    border: '1px solid var(--color-border)',
    borderRadius: 6,
    color: 'var(--color-popover-foreground)',
    fontSize: 12,
} as const;
