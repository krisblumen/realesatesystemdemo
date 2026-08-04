import React from 'react';

export interface BadgeProps extends React.HTMLAttributes<HTMLSpanElement> {
  /** Color intent. @default "neutral" */
  variant?: 'neutral' | 'navy' | 'orange' | 'success' | 'warning' | 'danger';
  /** Solid fill instead of soft tint. @default false */
  solid?: boolean;
  children?: React.ReactNode;
}

/**
 * Small uppercase pill for operation type (Venta/Renta), commercial status
 * (Preventa, Vendido, Disponible) and zone tags.
 */
export function Badge(props: BadgeProps): JSX.Element;
