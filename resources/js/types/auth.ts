export type Role =
    'super_admin' | 'exploitation_admin' | 'technician' | 'farmer';

export type UserExploitation = {
    id: number;
    name: string;
};

export type User = {
    id: number;
    name: string;
    phone: string;
    email: string | null;
    role: Role;
    role_label: string;
    exploitation: UserExploitation | null;
    avatar?: string;
};

export type Auth = {
    user: User;
};
