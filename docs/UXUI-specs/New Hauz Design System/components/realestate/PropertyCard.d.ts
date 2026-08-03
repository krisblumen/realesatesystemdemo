import React from 'react';

export interface PropertyCardProps extends React.HTMLAttributes<HTMLElement> {
  /** Cover photo URL. Falls back to a branded navy placeholder. */
  image?: string;
  title?: string;
  /** Zone / municipality line. */
  zone?: string;
  /** Formatted price string, e.g. "$8,950,000". */
  price?: string;
  /** @default "MXN" */
  currency?: string;
  /** Operation badge. @default "Venta" */
  operation?: 'Venta' | 'Renta' | 'Preventa';
  /** Optional status badge (e.g. "Vendido", "Disponible"). */
  status?: string;
  beds?: number;
  baths?: number;
  /** Built area in m². */
  area?: number;
  parking?: number;
}

/**
 * Signature property listing card — price-led, photo zoom + lift on hover,
 * spec row (beds / baths / m² / parking). Used in grids and search results.
 *
 * @startingPoint section="Real Estate" subtitle="Property listing card" viewport="380x420"
 */
export function PropertyCard(props: PropertyCardProps): JSX.Element;
