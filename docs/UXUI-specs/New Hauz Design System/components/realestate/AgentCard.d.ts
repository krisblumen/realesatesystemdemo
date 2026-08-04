import React from 'react';

export interface AgentCardProps extends React.HTMLAttributes<HTMLDivElement> {
  /** Agent photo URL; falls back to navy initials avatar. */
  photo?: string;
  name?: string;
  role?: string;
  /** Assigned zone(s). */
  zone?: string;
}

/**
 * Agent / asesor contact card with WhatsApp (green) + phone CTAs.
 * Used in the property-detail sticky panel and "Agentes por Zona".
 */
export function AgentCard(props: AgentCardProps): JSX.Element;
