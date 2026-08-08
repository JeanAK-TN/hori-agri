import { Link, usePage } from '@inertiajs/react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { navigationFor } from '@/lib/navigation';
import { cn } from '@/lib/utils';

/**
 * Primary navigation on a phone: a fixed bar with 48 px touch targets,
 * reachable with one thumb in the field (SPEC rule 5). Replaced by the
 * sidebar from the medium breakpoint upwards.
 */
export function BottomNav() {
    const { auth } = usePage().props;
    const { isCurrentUrl } = useCurrentUrl();

    if (!auth.user) {
        return null;
    }

    // Au-delà de cinq entrées, les cibles deviennent trop étroites au
    // pouce : le reste de la navigation reste accessible par le menu
    // latéral, que l'en-tête ouvre sur téléphone.
    const items = navigationFor(auth.user.role).slice(0, 5);

    if (items.length < 2) {
        return null;
    }

    return (
        <nav
            aria-label="Navigation principale"
            className="fixed inset-x-0 bottom-0 z-40 border-t bg-card pb-[env(safe-area-inset-bottom)] md:hidden"
        >
            <ul className="mx-auto flex max-w-2xl items-stretch justify-around">
                {items.map((item) => {
                    const active = isCurrentUrl(item.href);

                    return (
                        <li key={item.title} className="flex-1">
                            <Link
                                href={item.href}
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'flex min-h-14 flex-col items-center justify-center gap-1 px-1 py-2 text-xs font-medium transition-colors',
                                    active
                                        ? 'text-primary'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {item.icon && (
                                    <item.icon
                                        className={cn(
                                            'size-5',
                                            active && 'stroke-[2.5]',
                                        )}
                                    />
                                )}
                                <span>{item.shortTitle ?? item.title}</span>
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
