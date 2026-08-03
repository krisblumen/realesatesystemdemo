import React from 'react';

export interface IconProps {
  /** Icon key. */
  name: 'bed' | 'bath' | 'area' | 'car' | 'pin' | 'search' | 'phone' | 'whatsapp' | 'arrow' | 'heart' | 'check' | 'ruler';
  /** Pixel size. @default 18 */
  size?: number;
  /** Stroke width. @default 1.75 */
  stroke?: number;
  /** Stroke color. @default "currentColor" */
  color?: string;
}

/** Inline line-icon set (Lucide geometry) for property specs and CTAs. */
export function Icon(props: IconProps): JSX.Element;
