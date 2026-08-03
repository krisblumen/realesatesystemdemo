import React from 'react';

/**
 * Form input with floating label and orange focus ring (56px tall per guide).
 * Pass `as="textarea"` or `as="select"` to reuse the same shell.
 */
export function Input({
  label,
  hint,
  error,
  iconLeft = null,
  as = 'input',
  style = {},
  children,
  ...rest
}) {
  const [focused, setFocused] = React.useState(false);
  const Tag = as;
  const isSelect = as === 'select';
  const isArea = as === 'textarea';

  const fieldStyle = {
    width: '100%',
    height: isArea ? 'auto' : 'var(--control-h-input)',
    minHeight: isArea ? 120 : undefined,
    padding: iconLeft ? '0 18px 0 46px' : '0 18px',
    paddingTop: isArea ? 16 : undefined,
    paddingBottom: isArea ? 16 : undefined,
    fontFamily: 'var(--font-body)',
    fontSize: 15,
    color: 'var(--text-body)',
    background: '#fff',
    border: `1.5px solid ${error ? 'var(--nh-danger)' : focused ? 'var(--border-focus)' : 'var(--border-strong)'}`,
    borderRadius: 'var(--radius-md)',
    outline: 'none',
    boxShadow: focused && !error ? 'var(--ring-focus)' : 'none',
    transition: 'border-color var(--dur-base) var(--ease-standard), box-shadow var(--dur-base) var(--ease-standard)',
    appearance: isSelect ? 'none' : undefined,
    resize: isArea ? 'vertical' : undefined,
    ...style,
  };

  return (
    <label style={{ display: 'flex', flexDirection: 'column', gap: 7 }}>
      {label && (
        <span style={{ fontFamily: 'var(--font-display)', fontWeight: 600, fontSize: 13, color: 'var(--text-strong)', letterSpacing: '0.01em' }}>
          {label}
        </span>
      )}
      <span style={{ position: 'relative', display: 'block' }}>
        {iconLeft && (
          <span style={{ position: 'absolute', left: 16, top: '50%', transform: 'translateY(-50%)', color: 'var(--text-muted)', display: 'flex' }}>
            {iconLeft}
          </span>
        )}
        <Tag
          style={fieldStyle}
          onFocus={() => setFocused(true)}
          onBlur={() => setFocused(false)}
          {...rest}
        >
          {children}
        </Tag>
        {isSelect && (
          <span style={{ position: 'absolute', right: 16, top: '50%', transform: 'translateY(-50%)', pointerEvents: 'none', color: 'var(--text-muted)' }}>▾</span>
        )}
      </span>
      {(hint || error) && (
        <span style={{ fontFamily: 'var(--font-body)', fontSize: 12, color: error ? 'var(--nh-danger)' : 'var(--text-muted)' }}>
          {error || hint}
        </span>
      )}
    </label>
  );
}
