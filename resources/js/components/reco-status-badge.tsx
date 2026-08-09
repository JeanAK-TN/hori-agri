import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type RecoStatusValue = 'new' | 'read' | 'applied' | 'dismissed';

const TONES: Record<RecoStatusValue, string> = {
    new: 'bg-secondary text-secondary-foreground',
    read: 'bg-muted text-muted-foreground',
    applied: 'bg-severity-low text-severity-low-foreground',
    dismissed: 'bg-muted text-muted-foreground',
};

type Props = {
    status: RecoStatusValue;
    label: string;
    className?: string;
};

export default function RecoStatusBadge({ status, label, className }: Props) {
    return (
        <Badge className={cn('border-transparent', TONES[status], className)}>
            {label}
        </Badge>
    );
}
