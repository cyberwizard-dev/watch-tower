import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { dashboard, placeholder } from '@/routes/projects';
import exceptions from '@/routes/projects/exceptions';
import requests from '@/routes/projects/requests';
import type { CurrentProject, ProjectSummary } from '@/types/inertia';
import type { User } from '@/types/auth';
import { cn } from '@/lib/utils';

type ProjectShellProps = {
    project: CurrentProject;
    projects: ProjectSummary[];
    user: User | null;
    children: ReactNode;
};

type NavGroup = {
    label: string;
    items: NavItem[];
};

type NavItem = {
    label: string;
    href: string;
    matches: (path: string) => boolean;
    badge?: string;
};

export function ProjectShell({ project, projects, user, children }: ProjectShellProps) {
    const groups = navigationGroups(project.slug);
    const path = usePage().url;

    return (
        <div className="flex min-h-screen">
            <aside className="hidden w-60 shrink-0 flex-col border-r border-[#e6e7eb] bg-white dark:border-[#1d2129] dark:bg-[#0f1217] md:flex">
                <div className="flex h-14 items-center gap-3 border-b border-[#e6e7eb] px-4 dark:border-[#1d2129]">
                    <span className="grid h-7 w-7 place-items-center rounded-md bg-emerald-500 text-xs font-semibold text-white">
                        LW
                    </span>
                    <span className="text-sm font-semibold tracking-tight">LaravelWatch</span>
                </div>

                <ProjectSwitcher current={project} projects={projects} />

                <nav className="flex-1 space-y-6 px-3 py-4 text-sm">
                    {groups.map((group) => (
                        <div key={group.label}>
                            <div className="px-2 pb-1 text-[10px] font-semibold tracking-wider text-[#9aa0aa] uppercase">
                                {group.label}
                            </div>
                            <ul className="space-y-0.5">
                                {group.items.map((item) => {
                                    const active = item.matches(path);
                                    return (
                                        <li key={item.label}>
                                            <Link
                                                href={item.href}
                                                className={cn(
                                                    'flex items-center justify-between rounded-md px-2 py-1.5 text-[13px] transition-colors',
                                                    active
                                                        ? 'bg-[#eef1f6] font-medium text-[#1f2330] dark:bg-[#1a1f29] dark:text-white'
                                                        : 'text-[#5e6470] hover:bg-[#f1f3f7] hover:text-[#1f2330] dark:text-[#a0a6b1] dark:hover:bg-[#1a1f29] dark:hover:text-white',
                                                )}
                                            >
                                                <span>{item.label}</span>
                                                {item.badge ? (
                                                    <span className="rounded bg-[#eef1f6] px-1.5 py-0.5 text-[10px] font-medium text-[#5e6470] dark:bg-[#22283250] dark:text-[#a0a6b1]">
                                                        {item.badge}
                                                    </span>
                                                ) : null}
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    ))}
                </nav>

                <div className="border-t border-[#e6e7eb] px-4 py-3 text-xs dark:border-[#1d2129]">
                    <div className="flex items-center gap-2">
                        <span className="grid h-7 w-7 place-items-center rounded-full bg-[#1f2330] text-[11px] font-semibold text-white">
                            {(user?.name ?? 'G').charAt(0).toUpperCase()}
                        </span>
                        <div className="leading-tight">
                            <div className="font-medium text-[#1f2330] dark:text-white">{user?.name ?? 'Guest'}</div>
                            <div className="text-[#9aa0aa]">{user?.email ?? 'Not signed in'}</div>
                        </div>
                    </div>
                </div>
            </aside>

            <main className="flex flex-1 flex-col">{children}</main>
        </div>
    );
}

function ProjectSwitcher({ current, projects }: { current: CurrentProject; projects: ProjectSummary[] }) {
    return (
        <div className="border-b border-[#e6e7eb] px-3 py-3 dark:border-[#1d2129]">
            <label htmlFor="project-switch" className="text-[10px] font-semibold tracking-wider text-[#9aa0aa] uppercase">
                Project
            </label>
            <div className="mt-1 flex items-center gap-2">
                <span className="grid h-6 w-6 place-items-center rounded-md bg-[#1f2330] text-[11px] font-semibold text-white">
                    {current.name.charAt(0)}
                </span>
                <select
                    id="project-switch"
                    className="w-full appearance-none bg-transparent text-sm font-medium text-[#1f2330] outline-none dark:text-white"
                    value={current.slug}
                    onChange={(event) => {
                        window.location.href = dashboard(event.target.value).url;
                    }}
                >
                    {projects.map((project) => (
                        <option key={project.id} value={project.slug}>
                            {project.name}
                        </option>
                    ))}
                </select>
            </div>
            <div className="mt-1 text-[10px] uppercase tracking-wider text-[#9aa0aa]">{current.environment}</div>
        </div>
    );
}

function navigationGroups(slug: string): NavGroup[] {
    return [
        {
            label: 'Activity',
            items: [
                { label: 'Dashboard', href: dashboard(slug).url, matches: (p) => p.endsWith('/dashboard') },
                {
                    label: 'Requests',
                    href: requests.index(slug).url,
                    matches: (p) => p.endsWith('/requests') || p.includes('/requests?'),
                },
                {
                    label: 'Exceptions',
                    href: exceptions.index(slug).url,
                    matches: (p) => p.endsWith('/exceptions') || p.includes('/exceptions?'),
                },
                {
                    label: 'Jobs',
                    href: placeholder({ project: slug, section: 'jobs' }).url,
                    matches: (p) => p.endsWith('/jobs'),
                },
                {
                    label: 'Commands',
                    href: placeholder({ project: slug, section: 'commands' }).url,
                    matches: (p) => p.endsWith('/commands'),
                },
                {
                    label: 'Schedule',
                    href: placeholder({ project: slug, section: 'schedule' }).url,
                    matches: (p) => p.endsWith('/schedule'),
                },
            ],
        },
        {
            label: 'Monitoring',
            items: [
                {
                    label: 'Queries',
                    href: placeholder({ project: slug, section: 'queries' }).url,
                    matches: (p) => p.endsWith('/queries'),
                },
                {
                    label: 'Cache',
                    href: placeholder({ project: slug, section: 'cache' }).url,
                    matches: (p) => p.endsWith('/cache'),
                },
                {
                    label: 'Events',
                    href: placeholder({ project: slug, section: 'events' }).url,
                    matches: (p) => p.endsWith('/events'),
                },
                {
                    label: 'Notifications',
                    href: placeholder({ project: slug, section: 'notifications' }).url,
                    matches: (p) => p.endsWith('/notifications'),
                },
                {
                    label: 'Mail',
                    href: placeholder({ project: slug, section: 'mail' }).url,
                    matches: (p) => p.endsWith('/mail'),
                },
                {
                    label: 'Logs',
                    href: placeholder({ project: slug, section: 'logs' }).url,
                    matches: (p) => p.endsWith('/logs'),
                },
                {
                    label: 'Dumps',
                    href: placeholder({ project: slug, section: 'dumps' }).url,
                    matches: (p) => p.endsWith('/dumps'),
                },
                {
                    label: 'Views',
                    href: placeholder({ project: slug, section: 'views' }).url,
                    matches: (p) => p.endsWith('/views'),
                },
                {
                    label: 'Gates',
                    href: placeholder({ project: slug, section: 'gates' }).url,
                    matches: (p) => p.endsWith('/gates'),
                },
                {
                    label: 'Redis',
                    href: placeholder({ project: slug, section: 'redis' }).url,
                    matches: (p) => p.endsWith('/redis'),
                },
                {
                    label: 'HTTP Client',
                    href: placeholder({ project: slug, section: 'http-client' }).url,
                    matches: (p) => p.endsWith('/http-client'),
                },
                {
                    label: 'Models',
                    href: placeholder({ project: slug, section: 'models' }).url,
                    matches: (p) => p.endsWith('/models'),
                },
            ],
        },
    ];
}
