# Rollback Plan - New Hauz

## Objetivo

Definir procedimiento para regresar a una versión estable si un release falla.

---

# Cuándo ejecutar rollback

- Error 500 generalizado.
- Login roto.
- Admin inaccesible.
- Leads no se guardan.
- Migración fallida.
- Pérdida de datos.
- Performance inaceptable.

---

# Rollback de código

```bash
git checkout main
git reset --hard <commit-estable>
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

---

# Rollback de base de datos

Solo si aplica y con autorización de arquitectos.

```bash
psql -U newhauz_user -h 127.0.0.1 newhauz < backup_pre_release.sql
```

---

# Rollback de storage

```bash
tar -xzf storage_backup_pre_release.tar.gz
```

---

# Validación posterior

- [ ] Sitio carga
- [ ] Admin carga
- [ ] Login funciona
- [ ] Leads funcionan
- [ ] Inmuebles visibles
- [ ] Logs revisados

---

# Responsables

- Edgar: infraestructura y rollback técnico
- Kristian: validación funcional/UX
- Sebastián: validación QA post-rollback

---

# Regla
Todo rollback debe documentarse como incidente.
