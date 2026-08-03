# Auditoría de Diseño: RFC-059 THEME ADMIN + LOGIN

**Documento Auditado:** `docs/rfc/RFC-059-THEME-ADMIN-LOGIN.md`  
**Auditor:** Gemini CLI  
**Fecha:** 2026-06-17  
**Proyecto:** NEW HAUZ

---

## 1. Veredicto
**Resultado:** ✅ **APROBADO CON OBSERVACIONES**

### Resumen del Veredicto
El diseño propuesto en el RFC-059 es estéticamente superior, alineado con el posicionamiento "Premium" de New Hauz y técnicamente bien estructurado mediante el uso de `renderHooks` y el sistema de temas de Filament v3. Sin embargo, existe una **inconsistencia crítica de entorno**: el RFC asume que Filament v3 ya está instalado (RFC-004), pero las auditorías previas y el `composer.json` actual confirman que el paquete `filament/filament` no ha sido requerido aún en la rama `develop`.

---

## 2. Hallazgos Críticos (Bloqueantes)
1. **Ausencia de Dependencia Core:** El RFC indica en la sección "Consume de Épica 1" que Filament v3 está instalado. La realidad es que `composer.json` no contiene `filament/filament`. No se puede implementar el diseño sin el framework base.
2. **Inexistencia de `AdminPanelProvider.php`:** El archivo base de configuración del panel no existe en el sistema de archivos actual. El Lote A del RFC contempla su creación, pero debe marcarse como dependencia fuerte de la Épica 1.

---

## 3. Hallazgos Medios (Arquitectura/UX)
1. **Desviación del Color Primario:** El RFC propone `primary` => `#1E293B` (Slate/Navy), mientras que la `GUIA-UX-UI-newhauz.md` define el "Azul Corporativo" como `#091A5B`.
   * *Justificación del RFC:* Se usa un tono más neutro para el panel administrativo por sobriedad.
   * *Observación:* Es aceptable, pero debe quedar documentado que el `primary` de Filament no es el `brand-blue` de la marca pública para evitar confusiones en futuros módulos.
2. **Conflicto de Fusión (Merge Conflict):** La Épica 2 (`feature/epica-2-usuarios-y-seguridad`) está trabajando activamente en `AdminPanelProvider.php`. La implementación de este RFC debe ocurrir **después** del merge de la Épica 2 o mediante un rebase muy cuidadoso para no perder los middlewares de seguridad.

---

## 4. Hallazgos Menores
1. **Carga de Fuentes (CDN):** El archivo `theme.css` usa `@import` de Google Fonts. Esto puede causar un ligero FOUT (Flash of Unstyled Text) y depende de conectividad externa.
2. **Selectores Frágiles:** El uso de selectores como `.fi-simple-main` es correcto para Filament v3.x, pero requiere vigilancia en actualizaciones menores del framework.

---

## 5. Sobreingeniería Detectada
* No se detecta sobreingeniería. El uso de `renderHooks` para el footer es la forma idiomática y limpia de extender Filament sin sobrescribir vistas completas del vendor.

---

## 6. Riesgos de Implementación
1. **Punto de Montaje del Logo:** El uso de `margin-bottom: -1.25rem` para solapar el logo sobre la tarjeta glass es ingenioso pero depende de que Filament no cambie el padding del contenedor `.fi-simple-layout`.
2. **Compilación de Assets:** Al usar Tailwind CSS v4, el proceso de compilación del tema de Filament (`make:filament-theme`) debe integrarse correctamente con el `vite.config.js` principal para evitar duplicidad de CSS.

---

## 7. Riesgos de Seguridad
* **Visibilidad de Mensajes de Error:** Se ha validado que el diseño contempla el estado "Cuenta Suspendida". Es vital que el color `danger` (#C0392B) tenga suficiente contraste sobre la tarjeta glass en modo oscuro para cumplir con accesibilidad (WCAG 2.1).

---

## 8. Recomendaciones Obligatorias
1. **Pre-requisito:** Ejecutar `composer require filament/filament:"^3.2"` antes de iniciar el Lote A.
2. **Sincronización:** El Agente de Implementación debe realizar un `git pull origin develop` y asegurarse de que los cambios de la Épica 2 estén presentes antes de tocar el `AdminPanelProvider`.
3. **Consistencia:** Mantener el radio de borde en `16px` para la tarjeta de login, según lo estipulado para "Cards" en la Guía UX/UI.

---

## 9. Recomendaciones Opcionales
1. **Self-hosting de Fuentes:** Mover las fuentes Montserrat e Inter a local usando el plugin de Vite para mejorar el rendimiento y privacidad.
2. **Favicon Multi-resolución:** Proporcionar un `.ico` además del `.png` para máxima compatibilidad con navegadores antiguos.

---

## 10. Preguntas Abiertas
1. ¿El tamaño del logo (`2.5rem`) ha sido probado en pantallas móviles (iPhone SE / Mini) para asegurar que no empuje la tarjeta glass fuera del viewport?
2. ¿Se planea traducir el mensaje "By GESIF" o se mantendrá como firma de marca?

---

## 11. Checklist de Corrección (Para Claude - Agente de Implementación)
- [ ] Validar que `filament/filament` esté en `composer.json`.
- [ ] Asegurar que el `AdminPanelProvider` incluya los middlewares de la Épica 2 (`EnsureUserIsActive`).
- [ ] Verificar que el `theme.css` incluya los prefijos `-webkit-backdrop-filter` para compatibilidad con Safari/iOS.
- [ ] Confirmar que los assets en `docs/files-login-design/` coincidan exactamente con los tokens de color del RFC.

---

## 12. Checklist de Implementación (Para Codex - Agente de Programación)
- [ ] `php artisan filament:install --panels` (ID: admin).
- [ ] `php artisan make:filament-theme` (Panel: admin).
- [ ] Reemplazar `theme.css` con el contenido de la auditoría/diseño.
- [ ] Configurar `colors()`, `font()`, `brandLogo()` y `renderHook()` en `AdminPanelProvider`.
- [ ] `npm run build` y verificar que el archivo generado en `public/build/assets/` incluya los estilos de la tarjeta glass.
- [ ] Validar QA-026 a QA-036 según el RFC.
