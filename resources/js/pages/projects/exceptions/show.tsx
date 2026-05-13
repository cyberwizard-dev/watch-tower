import { usePage } from '@inertiajs/react';
import { Code2, Eye, Tag } from 'lucide-react';
import { useMemo, useState } from 'react';

import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { AppLayout } from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import exceptions from '@/routes/projects/exceptions';
import type { SharedProps } from '@/types/inertia';

type StackFrame = {
    file: string;
    line: number;
    function?: string | null;
    class?: string | null;
};

type ExceptionDetail = {
    id: string;
    exception_class: string;
    short_class: string;
    first_message: string;
    first_file: string | null;
    first_line: number | null;
    total_count: number;
    users_count: number;
    first_occurrence_at: string | null;
    last_occurrence_at: string | null;
    status: string;
    is_handled: boolean;
    framework_version: string | null;
    language_version: string | null;
    environments: { environment: string; count: number }[];
    sparkline: number[];
    latest_occurrence: {
        id: string;
        message: string;
        file: string | null;
        line: number | null;
        stacktrace: StackFrame[];
        context: Record<string, unknown>;
        occurred_at: string | null;
    } | null;
};

type Props = {
    exception: ExceptionDetail;
};

export default function ExceptionShow({ exception }: Props) {
    const { props } = usePage<SharedProps>();
    const slug = props.currentProject?.slug ?? '';

    return (
        <AppLayout title={exception.short_class}>
            <PageHeader
                title={exception.short_class}
                breadcrumbs={[
                    { label: 'Activity' },
                    { label: 'Exceptions', href: exceptions.index(slug).url },
                    { label: exception.short_class },
                ]}
            />

            <div className="grid grid-cols-1 gap-6 px-6 py-6 lg:grid-cols-[1fr_320px]">
                <div className="flex flex-col gap-6">
                    <HeaderSummary exception={exception} />
                    <StacktraceSection exception={exception} />
                </div>

                <aside className="flex flex-col gap-4">
                    <DetailsPanel exception={exception} />
                    <OccurrencesPanel environments={exception.environments} total={exception.total_count} />
                    <SparklinePanel sparkline={exception.sparkline} />
                </aside>
            </div>
        </AppLayout>
    );
}

function HeaderSummary({ exception }: { exception: ExceptionDetail }) {
    return (
        <Card className="p-5">
            <div className="flex flex-wrap items-center gap-2 text-xs">
                <Badge variant="muted" className="font-mono text-[10px]">
                    {exception.exception_class}
                </Badge>
                {exception.is_handled ? (
                    <Badge variant="muted" className="tracking-wide uppercase">
                        Handled
                    </Badge>
                ) : (
                    <Badge variant="destructive" className="tracking-wide uppercase">
                        Unhandled
                    </Badge>
                )}
                <StatusBadge status={exception.status} />
            </div>
            <p className="mt-3 text-base font-medium text-foreground">{exception.first_message}</p>
            <p className="mt-1 font-mono text-xs text-muted-foreground">
                {exception.first_file ?? 'unknown'}
                {exception.first_line ? `:${exception.first_line}` : ''}
            </p>

            <div className="mt-5 grid grid-cols-3 gap-4 border-t border-border pt-4">
                <Stat label="Events" value={exception.total_count.toLocaleString()} />
                <Stat label="Users" value={exception.users_count.toLocaleString()} />
                <Stat
                    label="Last seen"
                    value={formatRelative(exception.last_occurrence_at)}
                    hint={exception.last_occurrence_at ? formatAbsolute(exception.last_occurrence_at) : undefined}
                />
            </div>
        </Card>
    );
}

function StacktraceSection({ exception }: { exception: ExceptionDetail }) {
    const occurrence = exception.latest_occurrence;
    const frames = occurrence?.stacktrace ?? [];

    const [activeFrame, setActiveFrame] = useState(0);

    const selected = frames[activeFrame];

    const snippet = useMemo(() => buildSnippet(selected), [selected]);

    if (!occurrence || frames.length === 0) {
        return (
            <Card className="p-5">
                <div className="flex items-center gap-2 text-sm font-medium">
                    <Code2 className="h-4 w-4 text-muted-foreground" />
                    <span>Stacktrace</span>
                </div>
                <p className="mt-3 text-xs text-muted-foreground">No stacktrace captured for the latest occurrence.</p>
            </Card>
        );
    }

    return (
        <Card className="overflow-hidden">
            <div className="flex items-center justify-between border-b border-border px-4 py-2">
                <div className="flex items-center gap-2 text-sm font-medium">
                    <Code2 className="h-4 w-4 text-muted-foreground" />
                    <span>Stacktrace</span>
                </div>
                <span className="text-xs text-muted-foreground">
                    Latest event {occurrence.occurred_at ? `· ${formatRelative(occurrence.occurred_at)}` : ''}
                </span>
            </div>
            <div className="grid grid-cols-1 lg:grid-cols-[260px_1fr]">
                <ul className="max-h-[440px] divide-y divide-border overflow-y-auto">
                    {frames.map((frame, i) => (
                        <li key={i}>
                            <button
                                type="button"
                                onClick={() => setActiveFrame(i)}
                                className={cn(
                                    'w-full px-3 py-2 text-left transition-colors',
                                    i === activeFrame ? 'bg-muted/70' : 'hover:bg-muted/40',
                                )}
                            >
                                <div className="truncate font-mono text-[11px] text-foreground">{shortenFile(frame.file)}</div>
                                <div className="truncate text-[10px] text-muted-foreground">
                                    {frame.function ? `${frame.class ? frame.class + '::' : ''}${frame.function}` : ''}
                                    {' · '}
                                    line {frame.line}
                                </div>
                            </button>
                        </li>
                    ))}
                </ul>

                <div className="bg-background">
                    <div className="border-b border-border px-4 py-2 font-mono text-[11px] text-muted-foreground">
                        {selected?.file}:{selected?.line}
                    </div>
                    <pre className="overflow-x-auto px-4 py-3 font-mono text-xs leading-relaxed">
                        {snippet.map((entry) => (
                            <div
                                key={entry.line}
                                className={cn(
                                    'grid grid-cols-[40px_1fr] items-baseline gap-3',
                                    entry.highlighted && 'bg-destructive/10 text-foreground',
                                )}
                            >
                                <span className="text-right text-muted-foreground/60 select-none">{entry.line}</span>
                                <span>{entry.text}</span>
                            </div>
                        ))}
                    </pre>
                </div>
            </div>
        </Card>
    );
}

type SnippetLine = { line: number; text: string; highlighted: boolean };

function buildSnippet(frame: StackFrame | undefined): SnippetLine[] {
    if (!frame) {
        return [];
    }

    const target = frame.line;
    const start = Math.max(1, target - 4);
    const end = target + 4;

    const lines: SnippetLine[] = [];
    for (let i = start; i <= end; i++) {
        lines.push({
            line: i,
            text: i === target ? `// ${frame.function ?? 'method'} threw here` : '...',
            highlighted: i === target,
        });
    }
    return lines;
}

function shortenFile(file: string | null | undefined): string {
    if (!file) return '';
    return file.replace(/^.*?\/(app|vendor)\//, '$1/');
}

function DetailsPanel({ exception }: { exception: ExceptionDetail }) {
    return (
        <Card className="p-4">
            <SectionTitle icon={<Tag className="h-4 w-4" />}>Details</SectionTitle>
            <dl className="mt-3 space-y-2 text-xs">
                <DetailRow label="First seen" value={formatAbsolute(exception.first_occurrence_at)} />
                <DetailRow label="Last seen" value={formatAbsolute(exception.last_occurrence_at)} />
                <DetailRow label="Framework" value={exception.framework_version ? `Laravel ${exception.framework_version}` : '—'} />
                <DetailRow label="Language" value={exception.language_version ? `PHP ${exception.language_version}` : '—'} />
                <DetailRow label="Class" value={exception.exception_class} mono />
            </dl>
        </Card>
    );
}

function OccurrencesPanel({ environments, total }: { environments: { environment: string; count: number }[]; total: number }) {
    return (
        <Card className="p-4">
            <SectionTitle icon={<Eye className="h-4 w-4" />}>Occurrences</SectionTitle>
            <ul className="mt-3 space-y-2 text-xs">
                {environments.length === 0 && <li className="text-muted-foreground">No environment data</li>}
                {environments.map((row) => {
                    const pct = total > 0 ? Math.round((row.count / total) * 100) : 0;
                    return (
                        <li key={row.environment} className="flex flex-col gap-1">
                            <div className="flex items-center justify-between">
                                <span className="font-mono text-[11px] text-foreground capitalize">{row.environment}</span>
                                <span className="tabular-nums text-muted-foreground">
                                    {row.count.toLocaleString()} ({pct}%)
                                </span>
                            </div>
                            <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                <div className="h-full bg-emerald-500" style={{ width: `${pct}%` }} />
                            </div>
                        </li>
                    );
                })}
            </ul>
        </Card>
    );
}

function SparklinePanel({ sparkline }: { sparkline: number[] }) {
    const max = Math.max(1, ...sparkline);

    return (
        <Card className="p-4">
            <SectionTitle icon={<Eye className="h-4 w-4" />}>Last 14 days</SectionTitle>
            <div className="mt-3 flex h-16 items-end gap-1">
                {sparkline.map((value, i) => (
                    <div
                        key={i}
                        className="flex-1 rounded-sm bg-emerald-500/60"
                        style={{ height: `${Math.max(4, (value / max) * 100)}%` }}
                        title={`${value} event${value === 1 ? '' : 's'}`}
                    />
                ))}
            </div>
        </Card>
    );
}

function SectionTitle({ icon, children }: { icon: React.ReactNode; children: React.ReactNode }) {
    return (
        <div className="flex items-center gap-2 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
            {icon}
            <span>{children}</span>
        </div>
    );
}

function DetailRow({ label, value, mono }: { label: string; value: string; mono?: boolean }) {
    return (
        <div className="flex items-start justify-between gap-3">
            <dt className="shrink-0 text-muted-foreground">{label}</dt>
            <dd className={cn('text-right text-foreground', mono && 'font-mono text-[11px] break-all')}>{value}</dd>
        </div>
    );
}

function Stat({ label, value, hint }: { label: string; value: string; hint?: string }) {
    return (
        <div className="flex flex-col">
            <span className="text-sm font-semibold text-foreground tabular-nums">{value}</span>
            <span className="text-[10px] tracking-wider text-muted-foreground uppercase">{label}</span>
            {hint && <span className="mt-0.5 text-[10px] text-muted-foreground">{hint}</span>}
        </div>
    );
}

function StatusBadge({ status }: { status: string }) {
    const variant = status === 'resolved' ? 'success' : status === 'ignored' ? 'muted' : 'warning';
    return (
        <Badge variant={variant} className="tracking-wide uppercase">
            {status}
        </Badge>
    );
}

function formatRelative(iso: string | null): string {
    if (!iso) return '—';
    const date = new Date(iso);
    const diff = Date.now() - date.getTime();
    const seconds = Math.floor(diff / 1000);
    if (seconds < 60) return `${seconds}s ago`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    if (seconds < 86400 * 30) return `${Math.floor(seconds / 86400)}d ago`;
    return date.toLocaleDateString();
}

function formatAbsolute(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}
