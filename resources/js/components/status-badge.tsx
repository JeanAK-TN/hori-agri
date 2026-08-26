import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type CycleStatusValue =
    'planned' | 'in_progress' | 'harvested' | 'abandoned';

/**
 * Statut d'un cycle cultural, en paires tonales : fond pâle et texte
 * foncé, lisibles en plein soleil (design system AgriRepère).
 */
const TONES: Record<CycleStatusValue, string> = {
    planned: 'bg-muted text-muted-foreground',
    in_progress: 'bg-severity-low text-severity-low-foreground',
    harvested: 'bg-secondary text-secondary-foreground',
    abandoned: 'bg-severity-high text-severity-high-foreground',
};

type Props = {
    status: CycleStatusValue;
    label: string;
    className?: string;
};

export default function StatusBadge({ status, label, className }: Props) {
    return (
        <Badge className={cn('border-transparent', TONES[status], className)}>
            {label}
        </Badge>
    );
}
