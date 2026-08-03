import React from 'react';
import { Icon } from './Icon.jsx';

/**
 * Property search bar (buscador inmobiliario) — sits over the hero.
 * Operation · Type · Zone · Price + BUSCAR. White card, floats on imagery.
 */
export function SearchBar({ style = {}, onSearch, ...rest }) {
  const field = {
    display: 'flex', flexDirection: 'column', gap: 4, flex: 1, minWidth: 0,
    padding: '0 18px', borderRight: '1px solid var(--border-subtle)',
  };
  const labelS = { fontFamily: 'var(--font-display)', fontWeight: 600, fontSize: 11, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--text-muted)' };
  const selS = { border: 'none', outline: 'none', background: 'transparent', fontFamily: 'var(--font-body)', fontSize: 15, color: 'var(--text-strong)', fontWeight: 500, appearance: 'none', cursor: 'pointer', width: '100%' };

  return (
    <div
      style={{
        display: 'flex', alignItems: 'stretch',
        background: '#fff', borderRadius: 'var(--radius-lg)',
        boxShadow: 'var(--shadow-lg)', padding: 10, gap: 0,
        ...style,
      }}
      {...rest}
    >
      <label style={field}>
        <span style={labelS}>Operación</span>
        <select style={selS}><option>Venta</option><option>Renta</option><option>Preventa</option></select>
      </label>
      <label style={field}>
        <span style={labelS}>Tipo</span>
        <select style={selS}><option>Casa</option><option>Departamento</option><option>Terreno</option><option>Local</option></select>
      </label>
      <label style={field}>
        <span style={labelS}>Zona</span>
        <select style={selS}><option>Juriquilla</option><option>Zibatá</option><option>El Campanario</option><option>El Refugio</option><option>Cumbres del Lago</option></select>
      </label>
      <label style={{ ...field, borderRight: 'none' }}>
        <span style={labelS}>Precio</span>
        <select style={selS}><option>Hasta $5M</option><option>$5M – $10M</option><option>$10M – $20M</option><option>$20M+</option></select>
      </label>
      <button
        onClick={onSearch}
        onMouseEnter={(e) => (e.currentTarget.style.background = 'var(--cta-bg-hover)')}
        onMouseLeave={(e) => (e.currentTarget.style.background = 'var(--cta-bg)')}
        style={{
          display: 'inline-flex', alignItems: 'center', gap: 9,
          height: 'var(--control-h-input)', padding: '0 28px', border: 'none',
          borderRadius: 'var(--radius-md)', background: 'var(--cta-bg)', color: '#fff',
          fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 15, letterSpacing: '0.06em',
          cursor: 'pointer', transition: 'background var(--dur-base) var(--ease-standard)',
        }}
      >
        <Icon name="search" size={18} />
        BUSCAR
      </button>
    </div>
  );
}
