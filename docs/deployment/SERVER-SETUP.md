# Server Setup - New Hauz

## Objetivo

Definir la configuración base del servidor para producción.

---

# Requisitos recomendados

## VPS inicial

- 2 vCPU mínimo
- 4 GB RAM mínimo
- 80 GB SSD
- Ubuntu Server LTS
- Nginx
- PHP 8.3+
- PostgreSQL 16
- PostGIS
- Node.js 22+
- Composer 2.x
- Certbot o Cloudflare SSL

---

# Paquetes requeridos

```bash
sudo apt update
sudo apt upgrade -y

sudo apt install -y nginx git unzip curl supervisor
sudo apt install -y php php-cli php-fpm php-pgsql php-mbstring php-xml php-curl php-zip php-bcmath php-gd
sudo apt install -y postgresql postgresql-contrib
```

---

# Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

---

# Node.js

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

---

# Permisos Laravel

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

---

# Supervisor

Uso recomendado para:

- queues
- jobs
- notificaciones
- procesamiento de imágenes

---

# Checklist Servidor

- [ ] Nginx instalado
- [ ] PHP instalado
- [ ] PostgreSQL instalado
- [ ] PostGIS habilitado
- [ ] Composer instalado
- [ ] Node instalado
- [ ] Permisos Laravel configurados
- [ ] SSL activo
