import React from 'react';

/**
 * Status / category badge. Pill by default. Use for operation type
 * (Venta / Renta), commercial status (Preventa, Vendido) and zones.
 */
export function Badge({ children, variant = 'neutral', solid = false, style = {}, ...rest }) {
  const tints = {
    neutral: { bg: 'var(--nh-gray-100)', fg: 'var(--nh-gray-800)', solidBg: 'var(--nh-gray-800)' },
    navy: { bg: 'var(--nh-navy-50)', fg: 'var(--nh-navy)', solidBg: 'var(--nh-navy)' },
    orange: { bg: 'var(--nh-orange-50)', fg: 'var(--nh-orange-600)', solidBg: 'var(--nh-orange)' },
    success: { bg: 'var(--nh-success-bg)', fg: 'var(--nh-success)', solidBg: 'var(--nh-success)' },
    warning: { bg: 'var(--nh-warning-bg)', fg: 'var(--nh-warning)', solidBg: 'var(--nh-warning)' },
    danger: { bg: 'var(--nh-danger-bg)', fg: 'var(--nh-danger)', solidBg: 'var(--nh-danger)' },
  };
  const t = tints[variant] || tints.neutral;

  return (
    <span
      style={{
        display: 'inline-flex',
        alignItems: 'center',
        gap: 6,
        height: 26,
        padding: '0 12px',
        fontFamily: 'var(--font-display)',
        fontWeight: 600,
        fontSize: 12,
        letterSpacing: '0.04em',
        textTransform: 'uppercase',
        borderRadius: 'var(--radius-pill)',
        background: solid ? t.solidBg : t.bg,
        color: solid ? '#fff' : t.fg,
        whiteSpace: 'nowrap',
        ...style,
      }}
      {...rest}
    >
      {children}
    </span>
  );
}
