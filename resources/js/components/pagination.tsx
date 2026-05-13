import { Link } from '@inertiajs/react';

import type { PaginatedLink } from '@/types/pagination';
import { cn } from '@/lib/utils';

type PaginationProps = {
    links: PaginatedLink[];
    from: number | null;
    to: number | null;
    total: number;
};

export function Pagination({ links, from, to, total }: PaginationProps) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-[#e6e7eb] px-5 py-3 text-xs dark:border-[#1d2129]">
            <div className="text-[#5e6470] dark:text-[#a0a6b1]">
                Showing {from ?? 0}–{to ?? 0} of {total.toLocaleString()}
            </div>
            <div className="flex flex-wrap items-center gap-1">
                {links.map((link, i) => (
                    <Link
                        key={`${link.label}-${i}`}
                        href={link.url ?? '#'}
                        preserveScroll
                        only={[]}
                        className={cn(
                            'rounded px-2 py-1',
                            link.active
                                ? 'bg-[#1f2330] text-white dark:bg-emerald-500'
                                : 'border border-[#e6e7eb] text-[#5e6470] hover:text-[#1f2330] dark:border-[#1d2129] dark:text-[#a0a6b1] dark:hover:text-white',
                            !link.url && 'pointer-events-none opacity-40',
                        )}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ))}
            </div>
        </div>
    );
}
