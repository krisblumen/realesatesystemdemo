import React from 'react';
import { Icon } from './Icon.jsx';

/**
 * Agent / asesor contact card — used on property detail sticky panel and
 * "Agentes por Zona". Photo, name, zone, WhatsApp + phone CTAs.
 */
export function AgentCard({
  photo,
  name = 'Kristian Álvarez',
  role = 'Asesor Inmobiliario',
  zone = 'Juriquilla · Zibatá',
  style = {},
  ...rest
}) {
  const initials = name.split(' ').map((w) => w[0]).slice(0, 2).join('');
  return (
    <div style={{ background: '#fff', border: '1px solid var(--border-subtle)', borderRadius: 'var(--radius-lg)', padding: 20, boxShadow: 'var(--shadow-sm)', ...style }} {...rest}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
        <div style={{ width: 56, height: 56, borderRadius: '50%', overflow: 'hidden', flexShrink: 0, background: 'var(--nh-navy)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#fff', fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 18 }}>
          {photo ? <img src={photo} alt={name} style={{ width: '100%', height: '100%', objectFit: 'cover' }} /> : initials}
        </div>
        <div style={{ minWidth: 0 }}>
          <div style={{ fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 16, color: 'var(--text-strong)' }}>{name}</div>
          <div style={{ fontSize: 13, color: 'var(--text-muted)' }}>{role}</div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 5, fontSize: 12.5, color: 'var(--nh-navy-500)', marginTop: 3 }}>
            <Icon name="pin" size={13} color="var(--nh-orange)" />{zone}
          </div>
        </div>
      </div>
      <div style={{ display: 'flex', gap: 10, marginTop: 18 }}>
        <button style={{ flex: 1, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: 8, height: 46, border: 'none', borderRadius: 'var(--radius-md)', background: 'var(--nh-success)', color: '#fff', fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 14, cursor: 'pointer' }}>
          <Icon name="whatsapp" size={18} />WhatsApp
        </button>
        <button style={{ width: 46, height: 46, border: '1.5px solid var(--border-strong)', borderRadius: 'var(--radius-md)', background: '#fff', color: 'var(--nh-navy)', display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'pointer' }}>
          <Icon name="phone" size={18} />
        </button>
      </div>
    </div>
  );
}
