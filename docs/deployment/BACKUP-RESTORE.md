# Backup & Restore - New Hauz

## Objetivo

Definir estrategia de respaldo y restauración.

---

# Elementos a respaldar

- PostgreSQL
- Storage de imágenes
- Archivo `.env` de producción
- Configuración Nginx
- Certificados si aplica

---

# Backup PostgreSQL

```bash
pg_dump -U newhauz_user -h 127.0.0.1 newhauz > backup_newhauz_$(date +%Y%m%d).sql
```

---

# Restore PostgreSQL

```bash
psql -U newhauz_user -h 127.0.0.1 newhauz < backup_newhauz_YYYYMMDD.sql
```

---

# Backup Storage

```bash
tar -czf storage_backup_$(date +%Y%m%d).tar.gz storage/app/public
```

---

# Frecuencia recomendada

| Ambiente | Frecuencia |
|---|---|
| Production DB | Diario |
| Production Storage | Diario |
| Staging | Semanal |
| Local | Bajo demanda |

---

# Retención

- Diarios: 7 días
- Semanales: 4 semanas
- Mensuales: 6 meses

---

# Checklist Restore

- [ ] Backup validado
- [ ] Base destino disponible
- [ ] Restore ejecutado
- [ ] Migraciones verificadas
- [ ] Storage restaurado
- [ ] Sitio probado
