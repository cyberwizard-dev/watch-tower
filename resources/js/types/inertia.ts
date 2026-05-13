import type { User } from './auth';

export type ProjectSummary = {
    id: string;
    slug: string;
    name: string;
};

export type CurrentProject = ProjectSummary & {
    environment: string;
};

export type SharedProps = {
    name: string;
    auth: { user: User | null };
    currentProject: CurrentProject | null;
    projects: ProjectSummary[];
};
