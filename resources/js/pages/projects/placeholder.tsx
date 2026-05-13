import { PageHeader } from '@/components/page-header';
import { AppLayout } from '@/layouts/app-layout';

type Props = { section: string };

export default function Placeholder({ section }: Props) {
    const title = section.split('-').map((s) => s.charAt(0).toUpperCase() + s.slice(1)).join(' ');

    return (
        <AppLayout title={title}>
            <PageHeader title={title} breadcrumbs={[{ label: 'Monitoring' }, { label: title }]} />
            <div className="px-6 py-6">
                <div className="rounded-lg border border-dashed border-[#e6e7eb] bg-white p-12 text-center shadow-sm dark:border-[#1d2129] dark:bg-[#0f1217]">
                    <h2 className="text-lg font-semibold">{title} are coming soon</h2>
                    <p className="mt-2 text-sm text-[#5e6470] dark:text-[#a0a6b1]">
                        This section is part of the LaravelWatch MVP roadmap but isn't wired up yet.
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
