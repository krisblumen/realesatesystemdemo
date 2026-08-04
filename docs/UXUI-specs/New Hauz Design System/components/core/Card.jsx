import React from 'react';

/**
 * Generic surface card — 16px radius, soft shadow, optional hover elevation.
 * Building block for content blocks, service tiles, stat panels.
 */
export function Card({ children, hover = true, padding = 24, dark = false, style = {}, ...rest }) {
  const [h, setH] = React.useState(false);
  return (
    <div
      onMouseEnter={() => hover && setH(true)}
      onMouseLeave={() => hover && setH(false)}
      style={{
        background: dark ? 'var(--surface-dark)' : 'var(--surface-card)',
        color: dark ? 'var(--text-on-dark)' : 'var(--text-body)',
        border: dark ? '1px solid rgba(255,255,255,0.08)' : '1px solid var(--border-subtle)',
        borderRadius: 'var(--radius-lg)',
        padding,
        boxShadow: h ? 'var(--shadow-lg)' : 'var(--shadow-sm)',
        transform: h ? 'translateY(-3px)' : 'none',
        transition: 'box-shadow var(--dur-slow) var(--ease-out), transform var(--dur-slow) var(--ease-out)',
        ...style,
      }}
      {...rest}
    >
      {children}
    </div>
  );
}
