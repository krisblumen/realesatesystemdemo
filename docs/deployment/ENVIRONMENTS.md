# Environments - New Hauz

## Objetivo

Definir los ambientes oficiales para desarrollo, validación y producción.

---

# Ambientes

| Ambiente | Uso | Responsable |
|---|---|---|
| Local | Desarrollo individual | Kristian / Edgar |
| Development | Integración técnica | Edgar |
| Staging | QA / UAT | Sebastián |
| Production | Sitio público | Arquitectos |

---

# Local

Uso:

- Desarrollo de RFCs.
- Pruebas unitarias.
- Validación rápida.

Características:

- Docker local.
- PostgreSQL/PostGIS local.
- APP_DEBUG=true.

---

# Development

Uso:

- Integración de ramas.
- Validación técnica previa a QA.

Características:

- Base de datos persistente.
- Datos semilla.
- APP_DEBUG=true o controlado.

---

# Staging

Uso:

- QA formal.
- UAT.
- Pruebas de regresión.

Características:

- APP_DEBUG=false.
- Datos similares a producción.
- Dominio temporal protegido.
- Mismos servicios que producción.

---

# Production

Uso:

- Operación real.

Características:

- APP_DEBUG=false.
- HTTPS obligatorio.
- Backups activos.
- Logs monitoreados.
- Cloudflare activo.

---

# Reglas

1. Ningún cambio pasa a producción sin QA aprobado.
2. Staging debe replicar producción lo más posible.
3. Las credenciales no deben versionarse.
4. Las migraciones deben probarse antes en staging.
