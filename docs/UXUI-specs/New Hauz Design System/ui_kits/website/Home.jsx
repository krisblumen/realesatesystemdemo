/* New Hauz — Home (Inicio): hero + search, services, featured properties, investor band. */

function Home({ onNav }) {
  const props = [
    { title: 'Residencia El Campanario', zone: 'El Campanario, Qro.', price: '$12,400,000', operation: 'Venta', beds: 4, baths: 4, area: 420, parking: 3 },
    { title: 'Casa de Autor en Zibatá', zone: 'Zibatá, Querétaro', price: '$8,950,000', operation: 'Venta', status: 'Preventa', beds: 3, baths: 3, area: 280, parking: 2 },
    { title: 'Departamento Juriquilla', zone: 'Juriquilla, Querétaro', price: '$32,000', currency: 'MXN/mes', operation: 'Renta', beds: 2, baths: 2, area: 140, parking: 1 },
  ];
  const services = [
    ['Arquitectura', 'Diseño y planeación arquitectónica de autor.'],
    ['Construcción', 'Ejecución y supervisión profesional de obra.'],
    ['Comercialización', 'Venta y renta de inmuebles premium.'],
    ['Inversión', 'Desarrollo y análisis de oportunidades.'],
  ];
  return (
    <div>
      {/* HERO */}
      <section style={{ position: 'relative', minHeight: 560, display: 'flex', flexDirection: 'column', justifyContent: 'center', padding: '80px 24px 120px', background: 'linear-gradient(180deg, rgba(5,15,56,.45), rgba(5,15,56,.82)), linear-gradient(125deg, #14246E 0%, #050F38 75%)', overflow: 'hidden' }}>
        <div style={{ maxWidth: 1200, margin: '0 auto', width: '100%' }}>
          <div style={{ fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 13, letterSpacing: '0.18em', textTransform: 'uppercase', color: 'var(--nh-orange)' }}>Firma Inmobiliaria Integral · Querétaro</div>
          <h1 style={{ fontFamily: 'var(--font-display)', fontWeight: 800, fontSize: 56, lineHeight: 1.04, letterSpacing: '-0.02em', color: '#fff', margin: '18px 0 0', maxWidth: 760 }}>
            Construimos Patrimonio,<br />Diseñamos Espacios,<br /><span style={{ color: 'var(--nh-orange)' }}>Comercializamos Oportunidades</span>
          </h1>
          <p style={{ fontFamily: 'var(--font-body)', fontSize: 18, lineHeight: 1.6, color: 'rgba(255,255,255,0.82)', maxWidth: 520, marginTop: 22 }}>
            Acompañamos al cliente durante todo el ciclo inmobiliario: diseño, construcción, comercialización e inversión.
          </p>
          <div style={{ display: 'flex', gap: 14, marginTop: 30 }}>
            <Button variant="primary" onClick={() => onNav('listing')}>Ver Propiedades</Button>
            <Button variant="ghost" style={{ color: '#fff', borderColor: 'rgba(255,255,255,0.55)' }}>Agenda una Asesoría</Button>
          </div>
        </div>
        <div style={{ maxWidth: 1100, margin: '0 auto', width: '100%', position: 'absolute', left: '50%', transform: 'translateX(-50%)', bottom: -40, padding: '0 24px' }}>
          <SearchBar onSearch={() => onNav('listing')} />
        </div>
      </section>

      {/* SERVICES */}
      <section style={{ maxWidth: 1200, margin: '0 auto', padding: '110px 24px 40px' }}>
        <div style={{ textAlign: 'center', marginBottom: 44 }}>
          <div className="nh-eyebrow">Qué Hacemos</div>
          <h2 style={{ fontSize: 34, marginTop: 10 }}>Una Firma, Todo el Ciclo Inmobiliario</h2>
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 22 }}>
          {services.map(([t, d], i) => (
            <Card key={t} padding={26}>
              <div style={{ fontFamily: 'var(--font-display)', fontWeight: 800, fontSize: 28, color: 'var(--nh-orange)' }}>0{i + 1}</div>
              <h3 style={{ fontSize: 19, margin: '14px 0 8px' }}>{t}</h3>
              <p style={{ fontFamily: 'var(--font-body)', fontSize: 14.5, lineHeight: 1.6, color: 'var(--text-muted)' }}>{d}</p>
            </Card>
          ))}
        </div>
      </section>

      {/* FEATURED PROPERTIES */}
      <section style={{ maxWidth: 1200, margin: '0 auto', padding: '60px 24px' }}>
        <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', marginBottom: 32 }}>
          <div>
            <div className="nh-eyebrow">Propiedades Destacadas</div>
            <h2 style={{ fontSize: 34, marginTop: 10 }}>Inmuebles Seleccionados</h2>
          </div>
          <Button variant="ghost" size="sm" onClick={() => onNav('listing')}>Ver Todas</Button>
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 24 }}>
          {props.map((p) => <PropertyCard key={p.title} {...p} />)}
        </div>
      </section>

      {/* INVESTOR BAND */}
      <section style={{ background: 'var(--nh-navy-900)', padding: '88px 24px', marginTop: 40 }}>
        <div style={{ maxWidth: 1000, margin: '0 auto', textAlign: 'center' }}>
          <div style={{ fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 13, letterSpacing: '0.16em', textTransform: 'uppercase', color: 'var(--nh-orange)' }}>Inversionistas</div>
          <h2 style={{ fontSize: 40, color: '#fff', margin: '16px auto 0', maxWidth: 820, lineHeight: 1.15 }}>
            Invertimos donde otros ven terrenos. Creamos valor donde otros ven metros cuadrados.
          </h2>
          <div style={{ marginTop: 30 }}>
            <Button variant="primary">Conocer Proyectos</Button>
          </div>
        </div>
      </section>
    </div>
  );
}

Object.assign(window, { Home });
