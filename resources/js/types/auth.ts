export type Role = 'super_admin' | 'coop_admin' | 'technician' | 'farmer';

export type UserCooperative = {
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
    cooperative: UserCooperative | null;
    avatar?: string;
};

export type Auth = {
    user: User;
};
