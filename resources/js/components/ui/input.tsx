import * as React from 'react';
import { cn } from '@/lib/utils';

export const Input = React.forwardRef<HTMLInputElement, React.InputHTMLAttributes<HTMLInputElement>>(
    ({ className, type = 'text', ...props }, ref) => (
        <input
            ref={ref}
            type={type}
            className={cn(
                'flex h-8 w-full rounded-md border border-input bg-card px-2.5 py-1 text-[13px] shadow-xs transition-colors',
                'placeholder:text-muted-foreground',
                'focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring',
                'disabled:cursor-not-allowed disabled:opacity-50',
                'aria-invalid:border-destructive aria-invalid:outline-destructive',
                className,
            )}
            {...props}
        />
    ),
);

Input.displayName = 'Input';
