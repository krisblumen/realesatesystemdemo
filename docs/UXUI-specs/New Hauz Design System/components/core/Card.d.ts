import React from 'react';

export interface CardProps extends React.HTMLAttributes<HTMLDivElement> {
  /** Lift + deepen shadow on hover. @default true */
  hover?: boolean;
  /** Inner padding in px. @default 24 */
  padding?: number;
  /** Premium dark (navy) surface variant. @default false */
  dark?: boolean;
  children?: React.ReactNode;
}

/**
 * Generic 16px-radius surface with soft navy-tinted shadow and hover elevation.
 * Base for service tiles, content panels and dark "Inversionistas" blocks.
 */
export function Card(props: CardProps): JSX.Element;
