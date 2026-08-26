import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type SeverityValue = 'high' | 'medium' | 'low';

/**
 * Sévérité d'une recommandation, en paires tonales : fond pâle et texte
 * foncé, jamais de fond saturé plein (design system AgriRepère).
 */
const TONES: Record<SeverityValue, string> = {
    high: 'bg-severity-high text-severity-high-foreground',
    medium: 'bg-severity-medium text-severity-medium-foreground',
    low: 'bg-severity-low text-severity-low-foreground',
};

type Props = {
    severity: SeverityValue;
    label: string;
    className?: string;
};

export default function SeverityBadge({ severity, label, className }: Props) {
    return (
        <Badge className={cn('border-transparent', TONES[severity], className)}>
            {label}
        </Badge>
    );
}
