/* Shared chrome: top navigation + footer for the New Hauz public site. */

function NavBar({ active, onNav }) {
  const items = ['Inicio', 'Nosotros', 'Servicios', 'Proyectos', 'Inmobiliaria', 'Inversionistas', 'Partners', 'Contacto'];
  return (
    <header style={{ position: 'sticky', top: 0, zIndex: 50, background: 'rgba(255,255,255,0.92)', backdropFilter: 'blur(10px)', borderBottom: '1px solid var(--border-subtle)' }}>
      <div style={{ maxWidth: 1200, margin: '0 auto', padding: '0 24px', height: 76, display: 'flex', alignItems: 'center', gap: 32 }}>
        <img src="../../assets/logos/newhauz-logo-color.png" alt="New Hauz" style={{ height: 40, cursor: 'pointer' }} onClick={() => onNav('home')} />
        <nav style={{ display: 'flex', gap: 26, marginLeft: 8 }}>
          {items.map((it) => {
            const key = it === 'Inicio' ? 'home' : it === 'Inmobiliaria' ? 'listing' : it.toLowerCase();
            const on = (active === 'home' && it === 'Inicio') || (active === 'listing' && it === 'Inmobiliaria');
            return (
              <a key={it} onClick={() => (it === 'Inmobiliaria' || it === 'Inicio') && onNav(key)}
                style={{ fontFamily: 'var(--font-display)', fontWeight: 600, fontSize: 14, color: on ? 'var(--nh-navy)' : 'var(--text-body)', cursor: 'pointer', whiteSpace: 'nowrap', borderBottom: on ? '2px solid var(--nh-orange)' : '2px solid transparent', paddingBottom: 4 }}>
                {it}
              </a>
            );
          })}
        </nav>
        <div style={{ marginLeft: 'auto' }}>
          <Button variant="primary" size="sm">Publicar Propiedad</Button>
        </div>
      </div>
    </header>
  );
}

function Footer() {
  const cols = [
    ['Servicios', ['Arquitectura', 'Construcción', 'Comercialización', 'Inversión']],
    ['Inmobiliaria', ['Comprar', 'Rentar', 'Vender', 'Valuación', 'Preventas']],
    ['Empresa', ['Nosotros', 'Proyectos', 'Partners', 'Contacto']],
  ];
  return (
    <footer style={{ background: 'var(--nh-navy-900)', color: 'rgba(255,255,255,0.7)', padding: '64px 24px 36px' }}>
      <div style={{ maxWidth: 1200, margin: '0 auto', display: 'grid', gridTemplateColumns: '1.4fr 1fr 1fr 1fr', gap: 40 }}>
        <div>
          <img src="../../assets/logos/newhauz-logo-on-dark.png" alt="New Hauz" style={{ height: 44, marginBottom: 18 }} />
          <p style={{ fontFamily: 'var(--font-body)', fontSize: 14, lineHeight: 1.7, maxWidth: 280 }}>
            Arquitectura, construcción, comercialización inmobiliaria e inversión de alto valor en Querétaro.
          </p>
        </div>
        {cols.map(([title, links]) => (
          <div key={title}>
            <div style={{ fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 13, letterSpacing: '0.06em', textTransform: 'uppercase', color: '#fff', marginBottom: 16 }}>{title}</div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
              {links.map((l) => <a key={l} style={{ fontFamily: 'var(--font-body)', fontSize: 14, cursor: 'pointer' }}>{l}</a>)}
            </div>
          </div>
        ))}
      </div>
      <div style={{ maxWidth: 1200, margin: '40px auto 0', paddingTop: 24, borderTop: '1px solid rgba(255,255,255,0.12)', display: 'flex', justifyContent: 'space-between', fontFamily: 'var(--font-body)', fontSize: 13 }}>
        <span>© 2026 New Hauz · Querétaro, México</span>
        <span>Construimos patrimonio.</span>
      </div>
    </footer>
  );
}

Object.assign(window, { NavBar, Footer });
