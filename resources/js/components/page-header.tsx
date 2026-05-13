import { Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

type Crumb = {
    label: string;
    href?: string;
};

type PageHeaderProps = {
    title: ReactNode;
    breadcrumbs?: Crumb[];
    selectedRange?: string;
    onRangeChange?: (range: string) => void;
    actions?: ReactNode;
};

const RANGES = ['15m', '1h', '24h', '7d', '14d', '30d'] as const;

export function PageHeader({ title, breadcrumbs, selectedRange, onRangeChange, actions }: PageHeaderProps) {
    return (
        <header className="border-b border-[#e6e7eb] bg-white px-6 py-4 dark:border-[#1d2129] dark:bg-[#0f1217]">
            {breadcrumbs ? (
                <nav className="mb-1 flex items-center gap-1 text-xs text-[#9aa0aa]">
                    {breadcrumbs.map((crumb, i) => (
                        <span key={i} className="flex items-center gap-1">
                            {crumb.href ? (
                                <Link href={crumb.href} className="hover:text-[#1f2330] dark:hover:text-white">
                                    {crumb.label}
                                </Link>
                            ) : (
                                <span>{crumb.label}</span>
                            )}
                            {i < breadcrumbs.length - 1 ? <span>/</span> : null}
                        </span>
                    ))}
                </nav>
            ) : null}

            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-xl font-semibold tracking-tight">{title}</h1>

                <div className="flex items-center gap-3">
                    {selectedRange ? <RangeTabs value={selectedRange} onChange={onRangeChange} /> : null}
                    {actions}
                </div>
            </div>
        </header>
    );
}

function RangeTabs({ value, onChange }: { value: string; onChange?: (range: string) => void }) {
    return (
        <div className="inline-flex items-center rounded-md border border-[#e6e7eb] bg-white p-0.5 text-xs shadow-sm dark:border-[#1d2129] dark:bg-[#0f1217]">
            {RANGES.map((range) => {
                const active = range === value;
                return (
                    <button
                        key={range}
                        type="button"
                        onClick={() => {
                            if (onChange) {
                                onChange(range);
                                return;
                            }
                            const url = new URL(window.location.href);
                            url.searchParams.set('range', range);
                            router.visit(url.pathname + url.search, { preserveScroll: true });
                        }}
                        className={cn(
                            'rounded px-2 py-1 transition-colors',
                            active
                                ? 'bg-[#1f2330] text-white shadow dark:bg-emerald-500'
                                : 'text-[#5e6470] hover:text-[#1f2330] dark:text-[#a0a6b1] dark:hover:text-white',
                        )}
                    >
                        {range}
                    </button>
                );
            })}
        </div>
    );
}
