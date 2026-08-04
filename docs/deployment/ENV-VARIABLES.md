# Environment Variables - New Hauz

## Objetivo

Definir variables necesarias para operación local, staging y producción.

---

# Variables base

```env
APP_NAME="New Hauz"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost
```

---

# Base de datos

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=newhauz
DB_USERNAME=newhauz_user
DB_PASSWORD=secure_password
```

---

# Cache / Queue / Session

```env
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=120
```

---

# Mail

```env
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=notificaciones@newhauz.com.mx
MAIL_FROM_NAME="New Hauz"
```

---

# Storage

```env
FILESYSTEM_DISK=public
```

Futuro S3/R2:

```env
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=auto
AWS_BUCKET=
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=true
```

---

# Google Maps

```env
GOOGLE_MAPS_API_KEY=
```

---

# Analytics

```env
GA_MEASUREMENT_ID=
META_PIXEL_ID=
```

---

# Reglas

1. Nunca versionar `.env`.
2. Mantener actualizado `.env.example`.
3. Producción debe usar `APP_DEBUG=false`.
4. Secrets reales solo en servidor o gestor seguro.
