# Nginx Config - New Hauz

## Objetivo

Definir configuración base de Nginx para Laravel.

---

# Configuración sugerida

```nginx
server {
    listen 80;
    server_name newhauz.com.mx www.newhauz.com.mx;

    root /var/www/newhauz/public;
    index index.php index.html;

    client_max_body_size 64M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

# Activar sitio

```bash
sudo ln -s /etc/nginx/sites-available/newhauz /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

# Consideraciones

- Producción debe operar con HTTPS.
- Cloudflare puede manejar SSL externo.
- client_max_body_size debe soportar imágenes inmobiliarias.
- public/ debe ser el document root.
