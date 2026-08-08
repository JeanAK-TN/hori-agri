import {
    Building2,
    CalendarRange,
    House,
    LandPlot,
    Sprout,
    Users,
    Wheat,
} from 'lucide-react';
import { dashboard } from '@/routes';
import { index as campaigns } from '@/routes/campaigns';
import { index as cooperatives } from '@/routes/cooperatives';
import { index as crops } from '@/routes/crops';
import { index as cycles } from '@/routes/cycles';
import { index as farmers } from '@/routes/farmers';
import { index as plots } from '@/routes/plots';
import { index as users } from '@/routes/users';
import type { NavItem, Role } from '@/types';

type RoleNavItem = NavItem & {
    /** Roles allowed to reach the screen (SPEC section 11). */
    roles: Role[];
    /** Shorter wording for the mobile bar. */
    shortTitle?: string;
};

const ALL_ROLES: Role[] = ['super_admin', 'coop_admin', 'technician', 'farmer'];

const navigation: RoleNavItem[] = [
    {
        title: 'Accueil',
        href: dashboard(),
        icon: House,
        roles: ALL_ROLES,
    },
    {
        title: 'Coopératives',
        href: cooperatives(),
        icon: Building2,
        roles: ['super_admin'],
        shortTitle: 'Coop.',
    },
    {
        title: 'Producteurs',
        href: farmers(),
        icon: Users,
        roles: ['coop_admin', 'technician'],
    },
    {
        title: 'Parcelles',
        href: plots(),
        icon: LandPlot,
        roles: ['coop_admin', 'technician'],
    },
    {
        title: 'Cycles culturaux',
        href: cycles(),
        icon: Sprout,
        roles: ['coop_admin', 'technician'],
        shortTitle: 'Cycles',
    },
    {
        title: 'Campagnes',
        href: campaigns(),
        icon: CalendarRange,
        roles: ['coop_admin', 'technician'],
    },
    {
        title: 'Référentiel',
        href: crops(),
        icon: Wheat,
        roles: ['super_admin'],
    },
    {
        title: 'Comptes',
        href: users(),
        icon: Users,
        roles: ['super_admin', 'coop_admin'],
    },
];

/**
 * Navigation entries a role may see. Hiding a link is never a security
 * measure on its own (SPEC section 5): every route is also guarded by
 * the EnsureRole middleware and by a policy.
 */
export function navigationFor(role: Role): RoleNavItem[] {
    return navigation.filter((item) => item.roles.includes(role));
}
