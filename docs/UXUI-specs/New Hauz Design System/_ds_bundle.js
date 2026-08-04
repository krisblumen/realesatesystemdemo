/* @ds-bundle: {"format":3,"namespace":"NewHauzDesignSystem_e288df","components":[{"name":"Badge","sourcePath":"components/core/Badge.jsx"},{"name":"Button","sourcePath":"components/core/Button.jsx"},{"name":"Card","sourcePath":"components/core/Card.jsx"},{"name":"Input","sourcePath":"components/core/Input.jsx"},{"name":"AgentCard","sourcePath":"components/realestate/AgentCard.jsx"},{"name":"Icon","sourcePath":"components/realestate/Icon.jsx"},{"name":"PropertyCard","sourcePath":"components/realestate/PropertyCard.jsx"},{"name":"SearchBar","sourcePath":"components/realestate/SearchBar.jsx"}],"sourceHashes":{"components/core/Badge.jsx":"2c86729b9336","components/core/Button.jsx":"c603714c2c1b","components/core/Card.jsx":"40c893135b39","components/core/Input.jsx":"13092b11e04a","components/realestate/AgentCard.jsx":"b1bcc0b9dded","components/realestate/Icon.jsx":"c158bc57a7c6","components/realestate/PropertyCard.jsx":"fccbb4c45538","components/realestate/SearchBar.jsx":"c71c3154c480","ui_kits/website/Chrome.jsx":"1fbc96ee01cd","ui_kits/website/Home.jsx":"ba338f70865a"},"inlinedExternals":[],"unexposedExports":[]} */

(() => {

const __ds_ns = (window.NewHauzDesignSystem_e288df = window.NewHauzDesignSystem_e288df || {});

const __ds_scope = {};

(__ds_ns.__errors = __ds_ns.__errors || []);

// components/core/Badge.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Status / category badge. Pill by default. Use for operation type
 * (Venta / Renta), commercial status (Preventa, Vendido) and zones.
 */
function Badge({
  children,
  variant = 'neutral',
  solid = false,
  style = {},
  ...rest
}) {
  const tints = {
    neutral: {
      bg: 'var(--nh-gray-100)',
      fg: 'var(--nh-gray-800)',
      solidBg: 'var(--nh-gray-800)'
    },
    navy: {
      bg: 'var(--nh-navy-50)',
      fg: 'var(--nh-navy)',
      solidBg: 'var(--nh-navy)'
    },
    orange: {
      bg: 'var(--nh-orange-50)',
      fg: 'var(--nh-orange-600)',
      solidBg: 'var(--nh-orange)'
    },
    success: {
      bg: 'var(--nh-success-bg)',
      fg: 'var(--nh-success)',
      solidBg: 'var(--nh-success)'
    },
    warning: {
      bg: 'var(--nh-warning-bg)',
      fg: 'var(--nh-warning)',
      solidBg: 'var(--nh-warning)'
    },
    danger: {
      bg: 'var(--nh-danger-bg)',
      fg: 'var(--nh-danger)',
      solidBg: 'var(--nh-danger)'
    }
  };
  const t = tints[variant] || tints.neutral;
  return /*#__PURE__*/React.createElement("span", _extends({
    style: {
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
      ...style
    }
  }, rest), children);
}
Object.assign(__ds_scope, { Badge });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Badge.jsx", error: String((e && e.message) || e) }); }

// components/core/Button.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * New Hauz Button — Montserrat, 52px tall, 12px radius, 200ms.
 * Variants: primary (orange CTA), secondary (navy), ghost (outline), dark, link.
 */
function Button({
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
  const heights = {
    sm: 40,
    md: 52,
    lg: 56
  };
  const pads = {
    sm: '0 18px',
    md: '0 28px',
    lg: '0 34px'
  };
  const fonts = {
    sm: 14,
    md: 15,
    lg: 16
  };
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
    whiteSpace: 'nowrap'
  };
  const variants = {
    primary: {
      background: 'var(--cta-bg)',
      color: 'var(--cta-text)',
      boxShadow: 'var(--shadow-cta)'
    },
    secondary: {
      background: 'var(--nh-navy)',
      color: '#fff'
    },
    ghost: {
      background: 'transparent',
      color: 'var(--nh-navy)',
      borderColor: 'var(--border-strong)'
    },
    dark: {
      background: 'var(--nh-navy-900)',
      color: '#fff'
    },
    link: {
      background: 'transparent',
      color: 'var(--accent)',
      height: 'auto',
      padding: 0,
      letterSpacing: '0.02em'
    }
  };
  const onEnter = e => {
    if (disabled) return;
    if (variant === 'primary') e.currentTarget.style.background = 'var(--cta-bg-hover)';
    if (variant === 'secondary') e.currentTarget.style.background = 'var(--nh-navy-700)';
    if (variant === 'ghost') {
      e.currentTarget.style.borderColor = 'var(--nh-navy)';
      e.currentTarget.style.background = 'var(--nh-navy-50)';
    }
    if (variant === 'dark') e.currentTarget.style.background = 'var(--nh-navy-700)';
    if (variant !== 'link') e.currentTarget.style.transform = 'translateY(-1px)';
  };
  const onLeave = e => {
    Object.assign(e.currentTarget.style, variants[variant]);
    e.currentTarget.style.transform = 'none';
  };
  return /*#__PURE__*/React.createElement("button", _extends({
    type: "button",
    disabled: disabled,
    onMouseEnter: onEnter,
    onMouseLeave: onLeave,
    style: {
      ...base,
      ...variants[variant],
      ...style
    }
  }, rest), iconLeft, children, iconRight);
}
Object.assign(__ds_scope, { Button });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Button.jsx", error: String((e && e.message) || e) }); }

// components/core/Card.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Generic surface card — 16px radius, soft shadow, optional hover elevation.
 * Building block for content blocks, service tiles, stat panels.
 */
function Card({
  children,
  hover = true,
  padding = 24,
  dark = false,
  style = {},
  ...rest
}) {
  const [h, setH] = React.useState(false);
  return /*#__PURE__*/React.createElement("div", _extends({
    onMouseEnter: () => hover && setH(true),
    onMouseLeave: () => hover && setH(false),
    style: {
      background: dark ? 'var(--surface-dark)' : 'var(--surface-card)',
      color: dark ? 'var(--text-on-dark)' : 'var(--text-body)',
      border: dark ? '1px solid rgba(255,255,255,0.08)' : '1px solid var(--border-subtle)',
      borderRadius: 'var(--radius-lg)',
      padding,
      boxShadow: h ? 'var(--shadow-lg)' : 'var(--shadow-sm)',
      transform: h ? 'translateY(-3px)' : 'none',
      transition: 'box-shadow var(--dur-slow) var(--ease-out), transform var(--dur-slow) var(--ease-out)',
      ...style
    }
  }, rest), children);
}
Object.assign(__ds_scope, { Card });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Card.jsx", error: String((e && e.message) || e) }); }

// components/core/Input.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Form input with floating label and orange focus ring (56px tall per guide).
 * Pass `as="textarea"` or `as="select"` to reuse the same shell.
 */
function Input({
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
    ...style
  };
  return /*#__PURE__*/React.createElement("label", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 7
    }
  }, label && /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: 'var(--font-display)',
      fontWeight: 600,
      fontSize: 13,
      color: 'var(--text-strong)',
      letterSpacing: '0.01em'
    }
  }, label), /*#__PURE__*/React.createElement("span", {
    style: {
      position: 'relative',
      display: 'block'
    }
  }, iconLeft && /*#__PURE__*/React.createElement("span", {
    style: {
      position: 'absolute',
      left: 16,
      top: '50%',
      transform: 'translateY(-50%)',
      color: 'var(--text-muted)',
      display: 'flex'
    }
  }, iconLeft), /*#__PURE__*/React.createElement(Tag, _extends({
    style: fieldStyle,
    onFocus: () => setFocused(true),
    onBlur: () => setFocused(false)
  }, rest), children), isSelect && /*#__PURE__*/React.createElement("span", {
    style: {
      position: 'absolute',
      right: 16,
      top: '50%',
      transform: 'translateY(-50%)',
      pointerEvents: 'none',
      color: 'var(--text-muted)'
    }
  }, "\u25BE")), (hint || error) && /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: 'var(--font-body)',
      fontSize: 12,
      color: error ? 'var(--nh-danger)' : 'var(--text-muted)'
    }
  }, error || hint));
}
Object.assign(__ds_scope, { Input });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Input.jsx", error: String((e && e.message) || e) }); }

// components/realestate/Icon.jsx
try { (() => {
/**
 * Inline line-icons (Lucide geometry, 1.75 stroke) used across New Hauz kits.
 * Keeps property cards/specs free of emoji or cartoonish glyphs.
 */
function Icon({
  name,
  size = 18,
  stroke = 1.75,
  color = 'currentColor',
  style = {}
}) {
  const paths = {
    bed: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
      d: "M2 4v16"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M2 8h18a2 2 0 0 1 2 2v10"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M2 17h20"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M6 8v9"
    })),
    bath: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
      d: "M9 6 6.5 3.5a1.5 1.5 0 0 0-1-.5C4.683 3 4 3.683 4 4.5V17a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"
    }), /*#__PURE__*/React.createElement("line", {
      x1: "10",
      x2: "8",
      y1: "5",
      y2: "7"
    }), /*#__PURE__*/React.createElement("line", {
      x1: "2",
      x2: "22",
      y1: "12",
      y2: "12"
    }), /*#__PURE__*/React.createElement("line", {
      x1: "7",
      x2: "7",
      y1: "19",
      y2: "21"
    }), /*#__PURE__*/React.createElement("line", {
      x1: "17",
      x2: "17",
      y1: "19",
      y2: "21"
    })),
    area: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
      d: "M3 3h7v7H3z"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M3 3l18 18"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M14 14h7v7h-7z"
    })),
    car: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
      d: "M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"
    }), /*#__PURE__*/React.createElement("circle", {
      cx: "7",
      cy: "17",
      r: "2"
    }), /*#__PURE__*/React.createElement("circle", {
      cx: "17",
      cy: "17",
      r: "2"
    })),
    pin: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
      d: "M20 10c0 4.4-8 12-8 12s-8-7.6-8-12a8 8 0 0 1 16 0Z"
    }), /*#__PURE__*/React.createElement("circle", {
      cx: "12",
      cy: "10",
      r: "3"
    })),
    search: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("circle", {
      cx: "11",
      cy: "11",
      r: "8"
    }), /*#__PURE__*/React.createElement("path", {
      d: "m21 21-4.3-4.3"
    })),
    phone: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
      d: "M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92Z"
    })),
    whatsapp: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
      d: "M21 11.5a8.38 8.38 0 0 1-8.5 8.5 8.5 8.5 0 0 1-3.8-.9L3 21l1.9-5.7a8.5 8.5 0 0 1-.9-3.8A8.38 8.38 0 0 1 12.5 3 8.5 8.5 0 0 1 21 11.5Z"
    })),
    arrow: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
      d: "M5 12h14"
    }), /*#__PURE__*/React.createElement("path", {
      d: "m12 5 7 7-7 7"
    })),
    heart: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
      d: "M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"
    })),
    check: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
      d: "M20 6 9 17l-5-5"
    })),
    ruler: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
      d: "M21.3 8.7 8.7 21.3a1 1 0 0 1-1.4 0l-4.6-4.6a1 1 0 0 1 0-1.4L15.3 2.7a1 1 0 0 1 1.4 0l4.6 4.6a1 1 0 0 1 0 1.4Z"
    }), /*#__PURE__*/React.createElement("path", {
      d: "m7.5 10.5 2 2"
    }), /*#__PURE__*/React.createElement("path", {
      d: "m10.5 7.5 2 2"
    }), /*#__PURE__*/React.createElement("path", {
      d: "m13.5 4.5 2 2"
    }), /*#__PURE__*/React.createElement("path", {
      d: "m4.5 13.5 2 2"
    }))
  };
  return /*#__PURE__*/React.createElement("svg", {
    width: size,
    height: size,
    viewBox: "0 0 24 24",
    fill: "none",
    stroke: color,
    strokeWidth: stroke,
    strokeLinecap: "round",
    strokeLinejoin: "round",
    style: {
      flexShrink: 0,
      display: 'block',
      ...style
    },
    "aria-hidden": "true"
  }, paths[name] || null);
}
Object.assign(__ds_scope, { Icon });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/realestate/Icon.jsx", error: String((e && e.message) || e) }); }

// components/realestate/AgentCard.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Agent / asesor contact card — used on property detail sticky panel and
 * "Agentes por Zona". Photo, name, zone, WhatsApp + phone CTAs.
 */
function AgentCard({
  photo,
  name = 'Kristian Álvarez',
  role = 'Asesor Inmobiliario',
  zone = 'Juriquilla · Zibatá',
  style = {},
  ...rest
}) {
  const initials = name.split(' ').map(w => w[0]).slice(0, 2).join('');
  return /*#__PURE__*/React.createElement("div", _extends({
    style: {
      background: '#fff',
      border: '1px solid var(--border-subtle)',
      borderRadius: 'var(--radius-lg)',
      padding: 20,
      boxShadow: 'var(--shadow-sm)',
      ...style
    }
  }, rest), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 14
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: 56,
      height: 56,
      borderRadius: '50%',
      overflow: 'hidden',
      flexShrink: 0,
      background: 'var(--nh-navy)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      color: '#fff',
      fontFamily: 'var(--font-display)',
      fontWeight: 700,
      fontSize: 18
    }
  }, photo ? /*#__PURE__*/React.createElement("img", {
    src: photo,
    alt: name,
    style: {
      width: '100%',
      height: '100%',
      objectFit: 'cover'
    }
  }) : initials), /*#__PURE__*/React.createElement("div", {
    style: {
      minWidth: 0
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-display)',
      fontWeight: 700,
      fontSize: 16,
      color: 'var(--text-strong)'
    }
  }, name), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 13,
      color: 'var(--text-muted)'
    }
  }, role), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 5,
      fontSize: 12.5,
      color: 'var(--nh-navy-500)',
      marginTop: 3
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "pin",
    size: 13,
    color: "var(--nh-orange)"
  }), zone))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 10,
      marginTop: 18
    }
  }, /*#__PURE__*/React.createElement("button", {
    style: {
      flex: 1,
      display: 'inline-flex',
      alignItems: 'center',
      justifyContent: 'center',
      gap: 8,
      height: 46,
      border: 'none',
      borderRadius: 'var(--radius-md)',
      background: 'var(--nh-success)',
      color: '#fff',
      fontFamily: 'var(--font-display)',
      fontWeight: 700,
      fontSize: 14,
      cursor: 'pointer'
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "whatsapp",
    size: 18
  }), "WhatsApp"), /*#__PURE__*/React.createElement("button", {
    style: {
      width: 46,
      height: 46,
      border: '1.5px solid var(--border-strong)',
      borderRadius: 'var(--radius-md)',
      background: '#fff',
      color: 'var(--nh-navy)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      cursor: 'pointer'
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "phone",
    size: 18
  }))));
}
Object.assign(__ds_scope, { AgentCard });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/realestate/AgentCard.jsx", error: String((e && e.message) || e) }); }

// components/realestate/PropertyCard.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Property listing card — the signature New Hauz commerce unit.
 * 16px radius, image zoom + elevation on hover, price-led, spec row.
 */
function PropertyCard({
  image,
  title = 'Residencia Contemporánea',
  zone = 'Juriquilla, Querétaro',
  price = '$8,950,000',
  currency = 'MXN',
  operation = 'Venta',
  status,
  beds = 3,
  baths = 3,
  area = 280,
  parking = 2,
  style = {},
  ...rest
}) {
  const [h, setH] = React.useState(false);
  return /*#__PURE__*/React.createElement("article", _extends({
    onMouseEnter: () => setH(true),
    onMouseLeave: () => setH(false),
    style: {
      background: 'var(--surface-card)',
      borderRadius: 'var(--radius-lg)',
      overflow: 'hidden',
      border: '1px solid var(--border-subtle)',
      boxShadow: h ? 'var(--shadow-lg)' : 'var(--shadow-sm)',
      transform: h ? 'translateY(-4px)' : 'none',
      transition: 'box-shadow var(--dur-slow) var(--ease-out), transform var(--dur-slow) var(--ease-out)',
      cursor: 'pointer',
      ...style
    }
  }, rest), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'relative',
      aspectRatio: '4 / 3',
      overflow: 'hidden',
      background: 'linear-gradient(135deg, #14246E, #050F38)'
    }
  }, image ? /*#__PURE__*/React.createElement("img", {
    src: image,
    alt: title,
    style: {
      width: '100%',
      height: '100%',
      objectFit: 'cover',
      transform: h ? 'scale(1.06)' : 'scale(1)',
      transition: 'transform var(--dur-slow) var(--ease-out)'
    }
  }) : /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      inset: 0,
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      color: 'rgba(255,255,255,0.5)',
      fontFamily: 'var(--font-display)',
      fontWeight: 700,
      letterSpacing: '0.18em',
      fontSize: 12
    }
  }, "FOTOGRAF\xCDA"), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      top: 14,
      left: 14,
      display: 'flex',
      gap: 8
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Badge, {
    variant: "orange",
    solid: true
  }, operation), status && /*#__PURE__*/React.createElement(__ds_scope.Badge, {
    variant: "navy",
    solid: true
  }, status)), /*#__PURE__*/React.createElement("button", {
    "aria-label": "Guardar",
    style: {
      position: 'absolute',
      top: 12,
      right: 12,
      width: 36,
      height: 36,
      borderRadius: '50%',
      border: 'none',
      background: 'rgba(255,255,255,0.92)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      cursor: 'pointer',
      color: 'var(--nh-navy)'
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "heart",
    size: 17
  }))), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: 20
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'baseline',
      gap: 6
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: 'var(--font-display)',
      fontWeight: 800,
      fontSize: 24,
      color: 'var(--nh-navy)',
      letterSpacing: '-0.01em'
    }
  }, price), /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: 'var(--font-body)',
      fontSize: 13,
      color: 'var(--text-muted)',
      fontWeight: 500
    }
  }, currency)), /*#__PURE__*/React.createElement("h3", {
    style: {
      fontFamily: 'var(--font-display)',
      fontWeight: 600,
      fontSize: 18,
      color: 'var(--text-strong)',
      margin: '10px 0 6px'
    }
  }, title), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 6,
      color: 'var(--text-muted)',
      fontSize: 14
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "pin",
    size: 15,
    color: "var(--nh-orange)"
  }), /*#__PURE__*/React.createElement("span", null, zone)), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 18,
      marginTop: 16,
      paddingTop: 16,
      borderTop: '1px solid var(--border-subtle)',
      color: 'var(--text-body)',
      fontSize: 13.5,
      fontWeight: 500
    }
  }, /*#__PURE__*/React.createElement(Spec, {
    icon: "bed",
    value: beds
  }), /*#__PURE__*/React.createElement(Spec, {
    icon: "bath",
    value: baths
  }), /*#__PURE__*/React.createElement(Spec, {
    icon: "area",
    value: `${area} m²`
  }), parking ? /*#__PURE__*/React.createElement(Spec, {
    icon: "car",
    value: parking
  }) : null)));
}
function Spec({
  icon,
  value
}) {
  return /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      gap: 6
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: icon,
    size: 17,
    color: "var(--nh-navy-500)"
  }), value);
}
Object.assign(__ds_scope, { PropertyCard });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/realestate/PropertyCard.jsx", error: String((e && e.message) || e) }); }

// components/realestate/SearchBar.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Property search bar (buscador inmobiliario) — sits over the hero.
 * Operation · Type · Zone · Price + BUSCAR. White card, floats on imagery.
 */
function SearchBar({
  style = {},
  onSearch,
  ...rest
}) {
  const field = {
    display: 'flex',
    flexDirection: 'column',
    gap: 4,
    flex: 1,
    minWidth: 0,
    padding: '0 18px',
    borderRight: '1px solid var(--border-subtle)'
  };
  const labelS = {
    fontFamily: 'var(--font-display)',
    fontWeight: 600,
    fontSize: 11,
    letterSpacing: '0.08em',
    textTransform: 'uppercase',
    color: 'var(--text-muted)'
  };
  const selS = {
    border: 'none',
    outline: 'none',
    background: 'transparent',
    fontFamily: 'var(--font-body)',
    fontSize: 15,
    color: 'var(--text-strong)',
    fontWeight: 500,
    appearance: 'none',
    cursor: 'pointer',
    width: '100%'
  };
  return /*#__PURE__*/React.createElement("div", _extends({
    style: {
      display: 'flex',
      alignItems: 'stretch',
      background: '#fff',
      borderRadius: 'var(--radius-lg)',
      boxShadow: 'var(--shadow-lg)',
      padding: 10,
      gap: 0,
      ...style
    }
  }, rest), /*#__PURE__*/React.createElement("label", {
    style: field
  }, /*#__PURE__*/React.createElement("span", {
    style: labelS
  }, "Operaci\xF3n"), /*#__PURE__*/React.createElement("select", {
    style: selS
  }, /*#__PURE__*/React.createElement("option", null, "Venta"), /*#__PURE__*/React.createElement("option", null, "Renta"), /*#__PURE__*/React.createElement("option", null, "Preventa"))), /*#__PURE__*/React.createElement("label", {
    style: field
  }, /*#__PURE__*/React.createElement("span", {
    style: labelS
  }, "Tipo"), /*#__PURE__*/React.createElement("select", {
    style: selS
  }, /*#__PURE__*/React.createElement("option", null, "Casa"), /*#__PURE__*/React.createElement("option", null, "Departamento"), /*#__PURE__*/React.createElement("option", null, "Terreno"), /*#__PURE__*/React.createElement("option", null, "Local"))), /*#__PURE__*/React.createElement("label", {
    style: field
  }, /*#__PURE__*/React.createElement("span", {
    style: labelS
  }, "Zona"), /*#__PURE__*/React.createElement("select", {
    style: selS
  }, /*#__PURE__*/React.createElement("option", null, "Juriquilla"), /*#__PURE__*/React.createElement("option", null, "Zibat\xE1"), /*#__PURE__*/React.createElement("option", null, "El Campanario"), /*#__PURE__*/React.createElement("option", null, "El Refugio"), /*#__PURE__*/React.createElement("option", null, "Cumbres del Lago"))), /*#__PURE__*/React.createElement("label", {
    style: {
      ...field,
      borderRight: 'none'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: labelS
  }, "Precio"), /*#__PURE__*/React.createElement("select", {
    style: selS
  }, /*#__PURE__*/React.createElement("option", null, "Hasta $5M"), /*#__PURE__*/React.createElement("option", null, "$5M \u2013 $10M"), /*#__PURE__*/React.createElement("option", null, "$10M \u2013 $20M"), /*#__PURE__*/React.createElement("option", null, "$20M+"))), /*#__PURE__*/React.createElement("button", {
    onClick: onSearch,
    onMouseEnter: e => e.currentTarget.style.background = 'var(--cta-bg-hover)',
    onMouseLeave: e => e.currentTarget.style.background = 'var(--cta-bg)',
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      gap: 9,
      height: 'var(--control-h-input)',
      padding: '0 28px',
      border: 'none',
      borderRadius: 'var(--radius-md)',
      background: 'var(--cta-bg)',
      color: '#fff',
      fontFamily: 'var(--font-display)',
      fontWeight: 700,
      fontSize: 15,
      letterSpacing: '0.06em',
      cursor: 'pointer',
      transition: 'background var(--dur-base) var(--ease-standard)'
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "search",
    size: 18
  }), "BUSCAR"));
}
Object.assign(__ds_scope, { SearchBar });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/realestate/SearchBar.jsx", error: String((e && e.message) || e) }); }

// ui_kits/website/Chrome.jsx
try { (() => {
/* Shared chrome: top navigation + footer for the New Hauz public site. */

function NavBar({
  active,
  onNav
}) {
  const items = ['Inicio', 'Nosotros', 'Servicios', 'Proyectos', 'Inmobiliaria', 'Inversionistas', 'Partners', 'Contacto'];
  return /*#__PURE__*/React.createElement("header", {
    style: {
      position: 'sticky',
      top: 0,
      zIndex: 50,
      background: 'rgba(255,255,255,0.92)',
      backdropFilter: 'blur(10px)',
      borderBottom: '1px solid var(--border-subtle)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 1200,
      margin: '0 auto',
      padding: '0 24px',
      height: 76,
      display: 'flex',
      alignItems: 'center',
      gap: 32
    }
  }, /*#__PURE__*/React.createElement("img", {
    src: "../../assets/logos/newhauz-logo-color.png",
    alt: "New Hauz",
    style: {
      height: 40,
      cursor: 'pointer'
    },
    onClick: () => onNav('home')
  }), /*#__PURE__*/React.createElement("nav", {
    style: {
      display: 'flex',
      gap: 26,
      marginLeft: 8
    }
  }, items.map(it => {
    const key = it === 'Inicio' ? 'home' : it === 'Inmobiliaria' ? 'listing' : it.toLowerCase();
    const on = active === 'home' && it === 'Inicio' || active === 'listing' && it === 'Inmobiliaria';
    return /*#__PURE__*/React.createElement("a", {
      key: it,
      onClick: () => (it === 'Inmobiliaria' || it === 'Inicio') && onNav(key),
      style: {
        fontFamily: 'var(--font-display)',
        fontWeight: 600,
        fontSize: 14,
        color: on ? 'var(--nh-navy)' : 'var(--text-body)',
        cursor: 'pointer',
        whiteSpace: 'nowrap',
        borderBottom: on ? '2px solid var(--nh-orange)' : '2px solid transparent',
        paddingBottom: 4
      }
    }, it);
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      marginLeft: 'auto'
    }
  }, /*#__PURE__*/React.createElement(Button, {
    variant: "primary",
    size: "sm"
  }, "Publicar Propiedad"))));
}
function Footer() {
  const cols = [['Servicios', ['Arquitectura', 'Construcción', 'Comercialización', 'Inversión']], ['Inmobiliaria', ['Comprar', 'Rentar', 'Vender', 'Valuación', 'Preventas']], ['Empresa', ['Nosotros', 'Proyectos', 'Partners', 'Contacto']]];
  return /*#__PURE__*/React.createElement("footer", {
    style: {
      background: 'var(--nh-navy-900)',
      color: 'rgba(255,255,255,0.7)',
      padding: '64px 24px 36px'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 1200,
      margin: '0 auto',
      display: 'grid',
      gridTemplateColumns: '1.4fr 1fr 1fr 1fr',
      gap: 40
    }
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("img", {
    src: "../../assets/logos/newhauz-logo-on-dark.png",
    alt: "New Hauz",
    style: {
      height: 44,
      marginBottom: 18
    }
  }), /*#__PURE__*/React.createElement("p", {
    style: {
      fontFamily: 'var(--font-body)',
      fontSize: 14,
      lineHeight: 1.7,
      maxWidth: 280
    }
  }, "Arquitectura, construcci\xF3n, comercializaci\xF3n inmobiliaria e inversi\xF3n de alto valor en Quer\xE9taro.")), cols.map(([title, links]) => /*#__PURE__*/React.createElement("div", {
    key: title
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-display)',
      fontWeight: 700,
      fontSize: 13,
      letterSpacing: '0.06em',
      textTransform: 'uppercase',
      color: '#fff',
      marginBottom: 16
    }
  }, title), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 10
    }
  }, links.map(l => /*#__PURE__*/React.createElement("a", {
    key: l,
    style: {
      fontFamily: 'var(--font-body)',
      fontSize: 14,
      cursor: 'pointer'
    }
  }, l)))))), /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 1200,
      margin: '40px auto 0',
      paddingTop: 24,
      borderTop: '1px solid rgba(255,255,255,0.12)',
      display: 'flex',
      justifyContent: 'space-between',
      fontFamily: 'var(--font-body)',
      fontSize: 13
    }
  }, /*#__PURE__*/React.createElement("span", null, "\xA9 2026 New Hauz \xB7 Quer\xE9taro, M\xE9xico"), /*#__PURE__*/React.createElement("span", null, "Construimos patrimonio.")));
}
Object.assign(window, {
  NavBar,
  Footer
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/website/Chrome.jsx", error: String((e && e.message) || e) }); }

// ui_kits/website/Home.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/* New Hauz — Home (Inicio): hero + search, services, featured properties, investor band. */

function Home({
  onNav
}) {
  const props = [{
    title: 'Residencia El Campanario',
    zone: 'El Campanario, Qro.',
    price: '$12,400,000',
    operation: 'Venta',
    beds: 4,
    baths: 4,
    area: 420,
    parking: 3
  }, {
    title: 'Casa de Autor en Zibatá',
    zone: 'Zibatá, Querétaro',
    price: '$8,950,000',
    operation: 'Venta',
    status: 'Preventa',
    beds: 3,
    baths: 3,
    area: 280,
    parking: 2
  }, {
    title: 'Departamento Juriquilla',
    zone: 'Juriquilla, Querétaro',
    price: '$32,000',
    currency: 'MXN/mes',
    operation: 'Renta',
    beds: 2,
    baths: 2,
    area: 140,
    parking: 1
  }];
  const services = [['Arquitectura', 'Diseño y planeación arquitectónica de autor.'], ['Construcción', 'Ejecución y supervisión profesional de obra.'], ['Comercialización', 'Venta y renta de inmuebles premium.'], ['Inversión', 'Desarrollo y análisis de oportunidades.']];
  return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("section", {
    style: {
      position: 'relative',
      minHeight: 560,
      display: 'flex',
      flexDirection: 'column',
      justifyContent: 'center',
      padding: '80px 24px 120px',
      background: 'linear-gradient(180deg, rgba(5,15,56,.45), rgba(5,15,56,.82)), linear-gradient(125deg, #14246E 0%, #050F38 75%)',
      overflow: 'hidden'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 1200,
      margin: '0 auto',
      width: '100%'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-display)',
      fontWeight: 700,
      fontSize: 13,
      letterSpacing: '0.18em',
      textTransform: 'uppercase',
      color: 'var(--nh-orange)'
    }
  }, "Firma Inmobiliaria Integral \xB7 Quer\xE9taro"), /*#__PURE__*/React.createElement("h1", {
    style: {
      fontFamily: 'var(--font-display)',
      fontWeight: 800,
      fontSize: 56,
      lineHeight: 1.04,
      letterSpacing: '-0.02em',
      color: '#fff',
      margin: '18px 0 0',
      maxWidth: 760
    }
  }, "Construimos Patrimonio,", /*#__PURE__*/React.createElement("br", null), "Dise\xF1amos Espacios,", /*#__PURE__*/React.createElement("br", null), /*#__PURE__*/React.createElement("span", {
    style: {
      color: 'var(--nh-orange)'
    }
  }, "Comercializamos Oportunidades")), /*#__PURE__*/React.createElement("p", {
    style: {
      fontFamily: 'var(--font-body)',
      fontSize: 18,
      lineHeight: 1.6,
      color: 'rgba(255,255,255,0.82)',
      maxWidth: 520,
      marginTop: 22
    }
  }, "Acompa\xF1amos al cliente durante todo el ciclo inmobiliario: dise\xF1o, construcci\xF3n, comercializaci\xF3n e inversi\xF3n."), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 14,
      marginTop: 30
    }
  }, /*#__PURE__*/React.createElement(Button, {
    variant: "primary",
    onClick: () => onNav('listing')
  }, "Ver Propiedades"), /*#__PURE__*/React.createElement(Button, {
    variant: "ghost",
    style: {
      color: '#fff',
      borderColor: 'rgba(255,255,255,0.55)'
    }
  }, "Agenda una Asesor\xEDa"))), /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 1100,
      margin: '0 auto',
      width: '100%',
      position: 'absolute',
      left: '50%',
      transform: 'translateX(-50%)',
      bottom: -40,
      padding: '0 24px'
    }
  }, /*#__PURE__*/React.createElement(SearchBar, {
    onSearch: () => onNav('listing')
  }))), /*#__PURE__*/React.createElement("section", {
    style: {
      maxWidth: 1200,
      margin: '0 auto',
      padding: '110px 24px 40px'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      textAlign: 'center',
      marginBottom: 44
    }
  }, /*#__PURE__*/React.createElement("div", {
    className: "nh-eyebrow"
  }, "Qu\xE9 Hacemos"), /*#__PURE__*/React.createElement("h2", {
    style: {
      fontSize: 34,
      marginTop: 10
    }
  }, "Una Firma, Todo el Ciclo Inmobiliario")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(4, 1fr)',
      gap: 22
    }
  }, services.map(([t, d], i) => /*#__PURE__*/React.createElement(Card, {
    key: t,
    padding: 26
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-display)',
      fontWeight: 800,
      fontSize: 28,
      color: 'var(--nh-orange)'
    }
  }, "0", i + 1), /*#__PURE__*/React.createElement("h3", {
    style: {
      fontSize: 19,
      margin: '14px 0 8px'
    }
  }, t), /*#__PURE__*/React.createElement("p", {
    style: {
      fontFamily: 'var(--font-body)',
      fontSize: 14.5,
      lineHeight: 1.6,
      color: 'var(--text-muted)'
    }
  }, d))))), /*#__PURE__*/React.createElement("section", {
    style: {
      maxWidth: 1200,
      margin: '0 auto',
      padding: '60px 24px'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'flex-end',
      justifyContent: 'space-between',
      marginBottom: 32
    }
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    className: "nh-eyebrow"
  }, "Propiedades Destacadas"), /*#__PURE__*/React.createElement("h2", {
    style: {
      fontSize: 34,
      marginTop: 10
    }
  }, "Inmuebles Seleccionados")), /*#__PURE__*/React.createElement(Button, {
    variant: "ghost",
    size: "sm",
    onClick: () => onNav('listing')
  }, "Ver Todas")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(3, 1fr)',
      gap: 24
    }
  }, props.map(p => /*#__PURE__*/React.createElement(PropertyCard, _extends({
    key: p.title
  }, p))))), /*#__PURE__*/React.createElement("section", {
    style: {
      background: 'var(--nh-navy-900)',
      padding: '88px 24px',
      marginTop: 40
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 1000,
      margin: '0 auto',
      textAlign: 'center'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-display)',
      fontWeight: 700,
      fontSize: 13,
      letterSpacing: '0.16em',
      textTransform: 'uppercase',
      color: 'var(--nh-orange)'
    }
  }, "Inversionistas"), /*#__PURE__*/React.createElement("h2", {
    style: {
      fontSize: 40,
      color: '#fff',
      margin: '16px auto 0',
      maxWidth: 820,
      lineHeight: 1.15
    }
  }, "Invertimos donde otros ven terrenos. Creamos valor donde otros ven metros cuadrados."), /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: 30
    }
  }, /*#__PURE__*/React.createElement(Button, {
    variant: "primary"
  }, "Conocer Proyectos")))));
}
Object.assign(window, {
  Home
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/website/Home.jsx", error: String((e && e.message) || e) }); }

__ds_ns.Badge = __ds_scope.Badge;

__ds_ns.Button = __ds_scope.Button;

__ds_ns.Card = __ds_scope.Card;

__ds_ns.Input = __ds_scope.Input;

__ds_ns.AgentCard = __ds_scope.AgentCard;

__ds_ns.Icon = __ds_scope.Icon;

__ds_ns.PropertyCard = __ds_scope.PropertyCard;

__ds_ns.SearchBar = __ds_scope.SearchBar;

})();
