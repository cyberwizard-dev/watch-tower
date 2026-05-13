import { router } from '@inertiajs/react';
import { Bar, BarChart, CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

import { PageHeader } from '@/components/page-header';
import { AppLayout } from '@/layouts/app-layout';

type ActivityBucket = {
    bucket: string;
    success: number;
    client_error: number;
    server_error: number;
    avg_duration: number | null;
    p95_duration: number | null;
};

type JobBucket = {
    bucket: string;
    total: number;
    failed: number;
};

type DashboardStats = {
    range: { from: string; to: string; label: string };
    activity: {
        requests: { total: number; success: number; client_error: number; server_error: number };
        duration: {
            min_ms: number | null;
            max_ms: number | null;
            avg_ms: number | null;
            p95_ms: number | null;
            p99_ms: number | null;
        };
        buckets: ActivityBucket[];
    };
    exceptions: { total: number; window_label: string };
    jobs: {
        total: number;
        failed: number;
        processed: number;
        released: number;
        duration: { min_ms: number | null; max_ms: number | null; avg_ms: number | null; p95_ms: number | null };
        buckets: JobBucket[];
    };
};

type Props = {
    stats: DashboardStats;
    selectedRange: string;
};

export default function Dashboard({ stats, selectedRange }: Props) {
    return (
        <AppLayout title="Dashboard">
            <PageHeader
                title="Dashboard"
                breadcrumbs={[{ label: 'Activity' }, { label: 'Dashboard' }]}
                selectedRange={selectedRange}
                onRangeChange={(range) => {
                    const url = new URL(window.location.href);
                    url.searchParams.set('range', range);
                    router.visit(url.pathname + url.search, { preserveScroll: true });
                }}
            />

            <div className="space-y-6 px-6 py-6">
                <section className="rounded-lg border border-[#e6e7eb] bg-white shadow-sm dark:border-[#1d2129] dark:bg-[#0f1217]">
                    <div className="flex items-center justify-between border-b border-[#e6e7eb] px-5 py-3 dark:border-[#1d2129]">
                        <h2 className="text-sm font-semibold tracking-tight">Activity</h2>
                        <span className="text-xs text-[#9aa0aa]">Requests over time</span>
                    </div>
                    <div className="grid gap-6 p-5 lg:grid-cols-2">
                        <RequestsPanel activity={stats.activity} />
                        <DurationPanel activity={stats.activity} />
                    </div>
                </section>

                <section className="rounded-lg border border-[#e6e7eb] bg-white shadow-sm dark:border-[#1d2129] dark:bg-[#0f1217]">
                    <div className="flex items-center justify-between border-b border-[#e6e7eb] px-5 py-3 dark:border-[#1d2129]">
                        <h2 className="text-sm font-semibold tracking-tight">Application</h2>
                        <span className="text-xs text-[#9aa0aa]">Exceptions &amp; queue jobs</span>
                    </div>
                    <div className="grid gap-6 p-5 lg:grid-cols-2">
                        <ExceptionsPanel exceptions={stats.exceptions} />
                        <JobsPanel jobs={stats.jobs} />
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}

function RequestsPanel({ activity }: { activity: DashboardStats['activity'] }) {
    const { requests, buckets } = activity;
    const data = buckets.map((b) => ({
        time: formatTime(b.bucket),
        success: b.success,
        client_error: b.client_error,
        server_error: b.server_error,
    }));

    return (
        <div>
            <div className="flex flex-wrap gap-x-8 gap-y-2 text-xs">
                <Stat label="Requests" value={formatNumber(requests.total)} accent="text-[#1f2330] dark:text-white" />
                <Stat label="Success" value={formatNumber(requests.success)} accent="text-emerald-600" />
                <Stat label="4xx" value={formatNumber(requests.client_error)} accent="text-amber-600" />
                <Stat label="5xx" value={formatNumber(requests.server_error)} accent="text-rose-600" />
            </div>

            <div className="mt-4 h-44">
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={data}>
                        <CartesianGrid stroke="#e6e7eb" strokeDasharray="3 3" vertical={false} />
                        <XAxis dataKey="time" tick={{ fontSize: 10, fill: '#9aa0aa' }} interval={Math.floor(data.length / 6)} />
                        <YAxis tick={{ fontSize: 10, fill: '#9aa0aa' }} width={28} />
                        <Tooltip cursor={{ fill: 'rgba(31,35,48,0.05)' }} contentStyle={tooltipStyle} />
                        <Bar dataKey="success" stackId="status" fill="#10b981" radius={[2, 2, 0, 0]} />
                        <Bar dataKey="client_error" stackId="status" fill="#f59e0b" radius={[2, 2, 0, 0]} />
                        <Bar dataKey="server_error" stackId="status" fill="#ef4444" radius={[2, 2, 0, 0]} />
                    </BarChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}

function DurationPanel({ activity }: { activity: DashboardStats['activity'] }) {
    const { duration, buckets } = activity;
    const data = buckets.map((b) => ({
        time: formatTime(b.bucket),
        avg: b.avg_duration ?? 0,
        p95: b.p95_duration ?? 0,
    }));

    return (
        <div>
            <div className="flex flex-wrap gap-x-8 gap-y-2 text-xs">
                <Stat label="Avg" value={formatMs(duration.avg_ms)} accent="text-[#1f2330] dark:text-white" />
                <Stat label="P95" value={formatMs(duration.p95_ms)} accent="text-indigo-600" />
                <Stat label="P99" value={formatMs(duration.p99_ms)} accent="text-violet-600" />
                <Stat label="Max" value={formatMs(duration.max_ms)} accent="text-rose-600" />
            </div>

            <div className="mt-4 h-44">
                <ResponsiveContainer width="100%" height="100%">
                    <LineChart data={data}>
                        <CartesianGrid stroke="#e6e7eb" strokeDasharray="3 3" vertical={false} />
                        <XAxis dataKey="time" tick={{ fontSize: 10, fill: '#9aa0aa' }} interval={Math.floor(data.length / 6)} />
                        <YAxis tick={{ fontSize: 10, fill: '#9aa0aa' }} width={28} unit="ms" />
                        <Tooltip contentStyle={tooltipStyle} formatter={(value) => `${Number(value).toFixed(0)} ms`} />
                        <Line type="monotone" dataKey="avg" stroke="#6366f1" strokeWidth={2} dot={false} />
                        <Line type="monotone" dataKey="p95" stroke="#8b5cf6" strokeWidth={2} dot={false} strokeDasharray="4 2" />
                    </LineChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}

function ExceptionsPanel({ exceptions }: { exceptions: DashboardStats['exceptions'] }) {
    if (exceptions.total === 0) {
        return (
            <div className="flex h-44 flex-col items-center justify-center rounded-md border border-dashed border-[#e6e7eb] text-center dark:border-[#1d2129]">
                <span className="text-3xl">🎉</span>
                <p className="mt-2 text-sm font-medium">No exceptions in {exceptions.window_label}</p>
                <p className="text-xs text-[#9aa0aa]">Everything is running smoothly</p>
            </div>
        );
    }

    return (
        <div className="flex h-full flex-col">
            <div className="flex items-baseline gap-3">
                <span className="text-4xl font-semibold text-rose-600">{formatNumber(exceptions.total)}</span>
                <span className="text-xs text-[#9aa0aa]">in {exceptions.window_label}</span>
            </div>
            <p className="mt-3 text-xs text-[#5e6470] dark:text-[#a0a6b1]">
                Visit the Exceptions page for grouped stack traces, status, and frequency.
            </p>
        </div>
    );
}

function JobsPanel({ jobs }: { jobs: DashboardStats['jobs'] }) {
    const data = jobs.buckets.map((b) => ({
        time: formatTime(b.bucket),
        total: b.total,
        failed: b.failed,
    }));

    return (
        <div>
            <div className="flex flex-wrap gap-x-8 gap-y-2 text-xs">
                <Stat label="Total" value={formatNumber(jobs.total)} accent="text-[#1f2330] dark:text-white" />
                <Stat label="Processed" value={formatNumber(jobs.processed)} accent="text-emerald-600" />
                <Stat label="Failed" value={formatNumber(jobs.failed)} accent="text-rose-600" />
                <Stat label="P95" value={formatMs(jobs.duration.p95_ms)} accent="text-indigo-600" />
            </div>

            <div className="mt-4 h-32">
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={data}>
                        <CartesianGrid stroke="#e6e7eb" strokeDasharray="3 3" vertical={false} />
                        <XAxis dataKey="time" tick={{ fontSize: 10, fill: '#9aa0aa' }} interval={Math.floor(data.length / 6)} />
                        <YAxis tick={{ fontSize: 10, fill: '#9aa0aa' }} width={28} />
                        <Tooltip cursor={{ fill: 'rgba(31,35,48,0.05)' }} contentStyle={tooltipStyle} />
                        <Bar dataKey="total" fill="#3b82f6" radius={[2, 2, 0, 0]} />
                        <Bar dataKey="failed" fill="#ef4444" radius={[2, 2, 0, 0]} />
                    </BarChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}

function Stat({ label, value, accent }: { label: string; value: string; accent: string }) {
    return (
        <div>
            <div className="text-[10px] uppercase tracking-wider text-[#9aa0aa]">{label}</div>
            <div className={`text-base font-semibold ${accent}`}>{value}</div>
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
    background: 'white',
    border: '1px solid #e6e7eb',
    borderRadius: 6,
    fontSize: 12,
} as const;
