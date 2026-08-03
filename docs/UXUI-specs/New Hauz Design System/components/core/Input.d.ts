import React from 'react';

export interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  /** Field label (Montserrat). */
  label?: string;
  /** Helper text under the field. */
  hint?: string;
  /** Error message — turns border/text red, overrides hint. */
  error?: string;
  /** Leading icon node. */
  iconLeft?: React.ReactNode;
  /** Render element: input | textarea | select. @default "input" */
  as?: 'input' | 'textarea' | 'select';
  children?: React.ReactNode;
}

/**
 * Lead-form field — 56px tall, orange focus ring, real-time validation states.
 * Use `as="select"` for dropdowns (operation, zone, type) and `as="textarea"` for messages.
 */
export function Input(props: InputProps): JSX.Element;
