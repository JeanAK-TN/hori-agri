import type { ReactNode } from 'react';

type Props = {
    title: string;
    description?: string;
    children?: ReactNode;
};

/**
 * Standard heading of a list or detail screen: title, one line of
 * context, and the main action on the right (stacked on small screens).
 */
export default function PageHeader({ title, description, children }: Props) {
    return (
        <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div className="space-y-1">
                <h1 className="font-heading text-2xl font-semibold tracking-tight">
                    {title}
                </h1>
                {description && (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>

            {children && (
                <div className="flex shrink-0 flex-wrap gap-2">{children}</div>
            )}
        </div>
    );
}
