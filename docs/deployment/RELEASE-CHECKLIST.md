# Release Checklist - New Hauz

## Objetivo

Validar todos los puntos críticos antes de liberar a producción.

---

# Pre-release

- [ ] RFCs cerrados
- [ ] Pull Requests aprobados
- [ ] QA aprobado
- [ ] Regresión aprobada
- [ ] UAT aprobado
- [ ] Backup generado
- [ ] Migraciones revisadas
- [ ] Variables de entorno revisadas
- [ ] Storage validado
- [ ] SEO validado

---

# Deploy

- [ ] Pull de código
- [ ] Composer install
- [ ] NPM build
- [ ] Migraciones
- [ ] Cache config
- [ ] Cache routes
- [ ] Cache views
- [ ] Queue restart
- [ ] Nginx reload si aplica

---

# Post-release

- [ ] Home carga
- [ ] Admin carga
- [ ] Login funciona
- [ ] Buscador funciona
- [ ] Formularios funcionan
- [ ] WhatsApp funciona
- [ ] Leads se guardan
- [ ] Logs sin errores críticos
- [ ] Sebastián aprueba smoke test

---

# Resultado

## PASS
Release aprobado.

## FAIL
Ejecutar rollback.
