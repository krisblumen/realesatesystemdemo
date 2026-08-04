import React from 'react';

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  /** Visual style. @default "primary" */
  variant?: 'primary' | 'secondary' | 'ghost' | 'dark' | 'link';
  /** @default "md" */
  size?: 'sm' | 'md' | 'lg';
  /** Full-width block button. @default false */
  block?: boolean;
  disabled?: boolean;
  iconLeft?: React.ReactNode;
  iconRight?: React.ReactNode;
  children?: React.ReactNode;
}

/**
 * Primary action control for New Hauz. Orange "primary" is the conversion CTA;
 * use "secondary"/"dark" navy for supporting actions and "ghost" on light surfaces.
 *
 * @startingPoint section="Core" subtitle="Buttons — primary, secondary, ghost, dark" viewport="700x180"
 */
export function Button(props: ButtonProps): JSX.Element;
