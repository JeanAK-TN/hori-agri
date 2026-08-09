import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

type Props = {
    label: string;
    value: string;
    hint?: string;
    icon?: LucideIcon;
    className?: string;
};

/**
 * Un nombre de tête. Trois valeurs ne méritent pas un graphique : elles
 * se lisent plus vite écrites (design system Hori Agri).
 */
export default function StatCard({
    label,
    value,
    hint,
    icon: Icon,
    className,
}: Props) {
    return (
        <div className={cn('rounded-xl border bg-card p-4 md:p-5', className)}>
            <div className="flex items-start justify-between gap-3">
                <p className="text-sm text-muted-foreground">{label}</p>
                {Icon && (
                    <Icon className="size-5 shrink-0 text-muted-foreground" />
                )}
            </div>
            <p className="mt-2 font-heading text-2xl font-semibold tabular-nums">
                {value}
            </p>
            {hint && (
                <p className="mt-1 text-sm text-muted-foreground">{hint}</p>
            )}
        </div>
    );
}
