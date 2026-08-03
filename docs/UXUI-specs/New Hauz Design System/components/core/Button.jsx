import React from 'react';

/**
 * New Hauz Button — Montserrat, 52px tall, 12px radius, 200ms.
 * Variants: primary (orange CTA), secondary (navy), ghost (outline), dark, link.
 */
export function Button({
  children,
  variant = 'primary',
  size = 'md',
  block = false,
  disabled = false,
  iconLeft = null,
  iconRight = null,
  style = {},
  ...rest
}) {
  const heights = { sm: 40, md: 52, lg: 56 };
  const pads = { sm: '0 18px', md: '0 28px', lg: '0 34px' };
  const fonts = { sm: 14, md: 15, lg: 16 };

  const base = {
    display: block ? 'flex' : 'inline-flex',
    width: block ? '100%' : 'auto',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    height: heights[size],
    padding: pads[size],
    fontFamily: 'var(--font-display)',
    fontWeight: 700,
    fontSize: fonts[size],
    letterSpacing: '0.03em',
    borderRadius: 'var(--radius-md)',
    border: '1.5px solid transparent',
    cursor: disabled ? 'not-allowed' : 'pointer',
    opacity: disabled ? 0.5 : 1,
    transition: 'background var(--dur-base) var(--ease-standard), color var(--dur-base) var(--ease-standard), box-shadow var(--dur-base) var(--ease-standard), transform var(--dur-base) var(--ease-standard)',
    whiteSpace: 'nowrap',
  };

  const variants = {
    primary: { background: 'var(--cta-bg)', color: 'var(--cta-text)', boxShadow: 'var(--shadow-cta)' },
    secondary: { background: 'var(--nh-navy)', color: '#fff' },
    ghost: { background: 'transparent', color: 'var(--nh-navy)', borderColor: 'var(--border-strong)' },
    dark: { background: 'var(--nh-navy-900)', color: '#fff' },
    link: { background: 'transparent', color: 'var(--accent)', height: 'auto', padding: 0, letterSpacing: '0.02em' },
  };

  const onEnter = (e) => {
    if (disabled) return;
    if (variant === 'primary') e.currentTarget.style.background = 'var(--cta-bg-hover)';
    if (variant === 'secondary') e.currentTarget.style.background = 'var(--nh-navy-700)';
    if (variant === 'ghost') { e.currentTarget.style.borderColor = 'var(--nh-navy)'; e.currentTarget.style.background = 'var(--nh-navy-50)'; }
    if (variant === 'dark') e.currentTarget.style.background = 'var(--nh-navy-700)';
    if (variant !== 'link') e.currentTarget.style.transform = 'translateY(-1px)';
  };
  const onLeave = (e) => {
    Object.assign(e.currentTarget.style, variants[variant]);
    e.currentTarget.style.transform = 'none';
  };

  return (
    <button
      type="button"
      disabled={disabled}
      onMouseEnter={onEnter}
      onMouseLeave={onLeave}
      style={{ ...base, ...variants[variant], ...style }}
      {...rest}
    >
      {iconLeft}
      {children}
      {iconRight}
    </button>
  );
}
