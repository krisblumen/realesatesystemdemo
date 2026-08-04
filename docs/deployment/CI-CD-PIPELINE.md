# CI/CD Pipeline - New Hauz

## Objetivo

Definir flujo de integración y despliegue continuo.

---

# Flujo base

1. Feature branch
2. Pull Request a develop
3. Tests
4. QA
5. Merge a develop
6. Deploy staging
7. UAT
8. Release
9. Deploy production

---

# Ramas

```text
main
develop
feature/rfc-xxx
fix/bug-xxx
release/vx.x.x
```

---

# Validaciones mínimas en pipeline

```bash
composer install --no-interaction --prefer-dist
npm ci
npm run build
php artisan test
```

---

# Deploy manual sugerido

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

---

# Checklist CI/CD

- [ ] Tests pasan
- [ ] Build frontend correcto
- [ ] Migraciones listas
- [ ] QA aprobado
- [ ] UAT aprobado
- [ ] Backup previo
- [ ] Deploy ejecutado
- [ ] Smoke test aprobado
