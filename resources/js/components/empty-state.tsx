import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

type Props = {
    icon?: LucideIcon;
    title: string;
    description?: string;
    children?: ReactNode;
};

export default function EmptyState({
    icon: Icon,
    title,
    description,
    children,
}: Props) {
    return (
        <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed px-6 py-12 text-center">
            {Icon && (
                <span className="flex size-12 items-center justify-center rounded-full bg-accent text-accent-foreground">
                    <Icon className="size-6" />
                </span>
            )}
            <div className="space-y-1">
                <p className="font-heading font-semibold">{title}</p>
                {description && (
                    <p className="mx-auto max-w-sm text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {children}
        </div>
    );
}
