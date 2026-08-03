# Docker - New Hauz

## Objetivo

Definir el entorno Docker local para desarrollo reproducible.

---

# Servicios mínimos

- app
- nginx
- postgres/postgis
- mailpit opcional
- redis opcional

---

# docker-compose.yml sugerido

```yaml
services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: newhauz_app
    volumes:
      - .:/var/www/html
    depends_on:
      - postgres

  nginx:
    image: nginx:alpine
    container_name: newhauz_nginx
    ports:
      - "8080:80"
    volumes:
      - .:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app

  postgres:
    image: postgis/postgis:16-3.4
    container_name: newhauz_postgres
    ports:
      - "5432:5432"
    environment:
      POSTGRES_DB: newhauz
      POSTGRES_USER: newhauz_user
      POSTGRES_PASSWORD: secure_password
    volumes:
      - postgres_data:/var/lib/postgresql/data

volumes:
  postgres_data:
```

---

# Comandos básicos

Levantar ambiente:

```bash
docker compose up -d --build
```

Entrar al contenedor app:

```bash
docker compose exec app bash
```

Ejecutar migraciones:

```bash
docker compose exec app php artisan migrate
```

Ejecutar seeders:

```bash
docker compose exec app php artisan db:seed
```

Apagar ambiente:

```bash
docker compose down
```

---

# Checklist Docker

- [ ] app activo
- [ ] nginx activo
- [ ] postgres activo
- [ ] PostGIS habilitado
- [ ] Laravel responde
- [ ] Migraciones ejecutan
