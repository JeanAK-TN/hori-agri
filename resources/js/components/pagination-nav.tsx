import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

type Props<T> = {
    paginator: Paginated<T>;
    label: string;
};

/**
 * Server-side pagination (SPEC section 11.4). Deliberately reduced to
 * previous / next with a counter: a long row of numbered pages is
 * unusable on a 360 px screen.
 */
export default function PaginationNav<T>({ paginator, label }: Props<T>) {
    const { current_page, last_page, from, to, total } = paginator;

    if (total === 0) {
        return null;
    }

    const previous = paginator.links.at(0)?.url ?? null;
    const next = paginator.links.at(-1)?.url ?? null;

    return (
        <nav
            className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            aria-label={`Pagination des ${label}`}
        >
            <p className="text-sm text-muted-foreground tabular-nums">
                {from}–{to} sur {total} {label}
            </p>

            {last_page > 1 && (
                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="lg"
                        className="h-12"
                        disabled={previous === null}
                        asChild={previous !== null}
                    >
                        {previous === null ? (
                            <span>
                                <ChevronLeft className="size-4" />
                                Précédent
                            </span>
                        ) : (
                            <Link href={previous} preserveScroll>
                                <ChevronLeft className="size-4" />
                                Précédent
                            </Link>
                        )}
                    </Button>

                    <span className="px-2 text-sm text-muted-foreground tabular-nums">
                        {current_page} / {last_page}
                    </span>

                    <Button
                        variant="outline"
                        size="lg"
                        className="h-12"
                        disabled={next === null}
                        asChild={next !== null}
                    >
                        {next === null ? (
                            <span>
                                Suivant
                                <ChevronRight className="size-4" />
                            </span>
                        ) : (
                            <Link href={next} preserveScroll>
                                Suivant
                                <ChevronRight className="size-4" />
                            </Link>
                        )}
                    </Button>
                </div>
            )}
        </nav>
    );
}
