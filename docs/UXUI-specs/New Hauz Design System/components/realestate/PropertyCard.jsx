import React from 'react';
import { Icon } from './Icon.jsx';
import { Badge } from '../core/Badge.jsx';

/**
 * Property listing card — the signature New Hauz commerce unit.
 * 16px radius, image zoom + elevation on hover, price-led, spec row.
 */
export function PropertyCard({
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

  return (
    <article
      onMouseEnter={() => setH(true)}
      onMouseLeave={() => setH(false)}
      style={{
        background: 'var(--surface-card)',
        borderRadius: 'var(--radius-lg)',
        overflow: 'hidden',
        border: '1px solid var(--border-subtle)',
        boxShadow: h ? 'var(--shadow-lg)' : 'var(--shadow-sm)',
        transform: h ? 'translateY(-4px)' : 'none',
        transition: 'box-shadow var(--dur-slow) var(--ease-out), transform var(--dur-slow) var(--ease-out)',
        cursor: 'pointer',
        ...style,
      }}
      {...rest}
    >
      {/* media */}
      <div style={{ position: 'relative', aspectRatio: '4 / 3', overflow: 'hidden', background: 'linear-gradient(135deg, #14246E, #050F38)' }}>
        {image ? (
          <img src={image} alt={title} style={{ width: '100%', height: '100%', objectFit: 'cover', transform: h ? 'scale(1.06)' : 'scale(1)', transition: 'transform var(--dur-slow) var(--ease-out)' }} />
        ) : (
          <div style={{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'rgba(255,255,255,0.5)', fontFamily: 'var(--font-display)', fontWeight: 700, letterSpacing: '0.18em', fontSize: 12 }}>
            FOTOGRAFÍA
          </div>
        )}
        <div style={{ position: 'absolute', top: 14, left: 14, display: 'flex', gap: 8 }}>
          <Badge variant="orange" solid>{operation}</Badge>
          {status && <Badge variant="navy" solid>{status}</Badge>}
        </div>
        <button
          aria-label="Guardar"
          style={{ position: 'absolute', top: 12, right: 12, width: 36, height: 36, borderRadius: '50%', border: 'none', background: 'rgba(255,255,255,0.92)', display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'pointer', color: 'var(--nh-navy)' }}
        >
          <Icon name="heart" size={17} />
        </button>
      </div>

      {/* body */}
      <div style={{ padding: 20 }}>
        <div style={{ display: 'flex', alignItems: 'baseline', gap: 6 }}>
          <span style={{ fontFamily: 'var(--font-display)', fontWeight: 800, fontSize: 24, color: 'var(--nh-navy)', letterSpacing: '-0.01em' }}>{price}</span>
          <span style={{ fontFamily: 'var(--font-body)', fontSize: 13, color: 'var(--text-muted)', fontWeight: 500 }}>{currency}</span>
        </div>

        <h3 style={{ fontFamily: 'var(--font-display)', fontWeight: 600, fontSize: 18, color: 'var(--text-strong)', margin: '10px 0 6px' }}>{title}</h3>

        <div style={{ display: 'flex', alignItems: 'center', gap: 6, color: 'var(--text-muted)', fontSize: 14 }}>
          <Icon name="pin" size={15} color="var(--nh-orange)" />
          <span>{zone}</span>
        </div>

        <div style={{ display: 'flex', gap: 18, marginTop: 16, paddingTop: 16, borderTop: '1px solid var(--border-subtle)', color: 'var(--text-body)', fontSize: 13.5, fontWeight: 500 }}>
          <Spec icon="bed" value={beds} />
          <Spec icon="bath" value={baths} />
          <Spec icon="area" value={`${area} m²`} />
          {parking ? <Spec icon="car" value={parking} /> : null}
        </div>
      </div>
    </article>
  );
}

function Spec({ icon, value }) {
  return (
    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
      <Icon name={icon} size={17} color="var(--nh-navy-500)" />
      {value}
    </span>
  );
}
