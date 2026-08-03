# Database Deployment - New Hauz

## Objetivo

Definir proceso de preparación y despliegue de PostgreSQL + PostGIS.

---

# Crear base

```sql
CREATE DATABASE newhauz;
CREATE USER newhauz_user WITH PASSWORD 'secure_password';
GRANT ALL PRIVILEGES ON DATABASE newhauz TO newhauz_user;
```

---

# Habilitar PostGIS

Conectarse a la base:

```bash
psql -U postgres -d newhauz
```

Ejecutar:

```sql
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS postgis_topology;
SELECT PostGIS_Version();
```

---

# Migraciones

```bash
php artisan migrate
```

---

# Seeders

```bash
php artisan db:seed
```

---

# Migración fresh en local/staging

```bash
php artisan migrate:fresh --seed
```

No usar en producción.

---

# Reglas producción

1. Nunca ejecutar `migrate:fresh`.
2. Respaldar antes de migraciones críticas.
3. Probar migraciones en staging.
4. Usar transacciones cuando aplique.
5. Documentar cambios de esquema.

---

# Checklist DB

- [ ] PostgreSQL activo
- [ ] Base creada
- [ ] Usuario creado
- [ ] PostGIS habilitado
- [ ] Migraciones ejecutadas
- [ ] Seeders ejecutados
- [ ] Backup previo generado
