# **Épica 1 — Fundación Técnica NEW HAUZ**

## **Épica 1 — Fundación Técnica**

### **RFC-001 al RFC-010**

**Proyecto:** Plataforma Monolítica New Hauz  
**Stack:** Laravel 13.x \+ Filament \+ PostgreSQL \+ PostGIS \+ Livewire \+ Tailwind CSS  
**Arquitecto responsable sugerido:** Edgar  
**QA:** Sebastián  
**UX/UI:** Kristian

---

# **1\. Objetivo de la Épica**

Preparar la base técnica del proyecto para que los módulos funcionales posteriores puedan desarrollarse sin fricción operativa.

Esta épica debe dejar listo:

* Proyecto Laravel 13.x.  
* Base de datos PostgreSQL.  
* Extensión PostGIS.  
* Panel administrativo Filament.  
* Livewire.  
* Roles y permisos.  
* Media Library.  
* Ambientes configurados.  
* Flujo Git.  
* Docker local.

---

# **2\. RFC-001 — Configuración Laravel 13**

## **Objetivo**

Inicializar el proyecto Laravel como monolito principal de New Hauz.

## **Pasos**

### **1\. Crear proyecto Laravel**

```shell
composer create-project laravel/laravel newhauz
cd newhauz
```

### **2\. Validar versión de Laravel**

```shell
php artisan --version
```

### **3\. Instalar dependencias iniciales**

```shell
composer install
npm install
```

### **4\. Ejecutar servidor local**

```shell
php artisan serve
```

### **5\. Compilar assets**

```shell
npm run dev
```

## **Entregables**

* Proyecto Laravel inicializado.  
* Estructura base funcional.  
* Servidor local levantando correctamente.

## **Validación QA**

| ID | Prueba | Resultado esperado |
| ----- | ----- | ----- |
| QA-RFC-001-01 | Ejecutar `php artisan --version` | Laravel responde correctamente |
| QA-RFC-001-02 | Ejecutar `php artisan serve` | Sitio carga en navegador |
| QA-RFC-001-03 | Ejecutar `npm run dev` | Assets compilan sin error |

## **Definition of Done**

* Laravel corre localmente.  
* No existen errores de dependencias.  
* Proyecto versionado en Git.

---

# **3\. RFC-002 — Configuración PostgreSQL**

## **Objetivo**

Migrar la base técnica del proyecto de SQLite a PostgreSQL.

## **Pasos**

### **1\. Instalar driver PostgreSQL**

En ambiente local validar que PHP tenga habilitado:

```shell
php -m | grep pgsql
```

Debe aparecer:

```shell
pdo_pgsql
pgsql
```

### **2\. Configurar** 

### **`.env`**

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=newhauz
DB_USERNAME=newhauz_user
DB_PASSWORD=secure_password
```

### **3\. Crear base de datos**

```sql
CREATE DATABASE newhauz;
CREATE USER newhauz_user WITH PASSWORD 'secure_password';
GRANT ALL PRIVILEGES ON DATABASE newhauz TO newhauz_user;
```

### **4\. Ejecutar migraciones**

```shell
php artisan migrate
```

## **Entregables**

* PostgreSQL configurado.  
* Laravel conectado a PostgreSQL.  
* Migraciones ejecutadas.

## **Validación QA**

| ID | Prueba | Resultado esperado |
| ----- | ----- | ----- |
| QA-RFC-002-01 | Ejecutar `php artisan migrate` | Migraciones correctas |
| QA-RFC-002-02 | Revisar `.env` | PostgreSQL configurado |
| QA-RFC-002-03 | Conectar a DB desde Laravel | Conexión exitosa |

## **Definition of Done**

* Proyecto ya no depende de SQLite.  
* Base PostgreSQL opera correctamente.  
* `.env.example` actualizado.

---

# **4\. RFC-003 — Instalación PostGIS**

## **Objetivo**

Habilitar capacidades geoespaciales para zonas, polígonos, coordenadas e inmuebles.

## **Pasos**

### **1\. Instalar PostGIS en PostgreSQL**

Con Docker o PostgreSQL local:

```sql
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS postgis_topology;
```

### **2\. Validar instalación**

```sql
SELECT PostGIS_Version();
```

### **3\. Crear migración de prueba**

```shell
php artisan make:migration test_postgis_extension
```

### **4\. Validar tipo geography / geometry**

Ejemplo futuro:

```php
$table->geometry('location')->nullable();
```

## **Entregables**

* PostGIS activo.  
* Base lista para zonas geográficas.  
* Validación técnica documentada.

## **Validación QA**

| ID | Prueba | Resultado esperado |
| ----- | ----- | ----- |
| QA-RFC-003-01 | Ejecutar `SELECT PostGIS_Version()` | Devuelve versión |
| QA-RFC-003-02 | Crear campo geometry de prueba | Migración exitosa |
| QA-RFC-003-03 | Ejecutar consulta geográfica simple | Consulta válida |

## **Definition of Done**

* PostGIS está habilitado.  
* Se puede usar geometry/geography.  
* Documentado en README técnico.

---

# **5\. RFC-004 — Instalación Filament**

## **Objetivo**

Instalar Filament como panel administrativo principal del sistema.

## **Pasos**

### **1\. Instalar Filament**

```shell
composer require filament/filament
```

### **2\. Ejecutar instalador**

```shell
php artisan filament:install --panels
```

### **3\. Crear usuario administrador inicial**

```shell
php artisan make:filament-user
```

### **4\. Acceder al panel**

Ruta esperada:

```
/admin
```

## **Entregables**

* Filament instalado.  
* Panel administrativo funcionando.  
* Usuario administrador creado.

## **Validación QA**

| ID | Prueba | Resultado esperado |
| ----- | ----- | ----- |
| QA-RFC-004-01 | Acceder a `/admin` | Login visible |
| QA-RFC-004-02 | Login admin | Acceso permitido |
| QA-RFC-004-03 | Usuario sin permisos accede | Acceso denegado |

## **Definition of Done**

* Panel disponible.  
* Login funcional.  
* Usuario admin inicial creado.

---

# **6\. RFC-005 — Instalación Livewire**

## **Objetivo**

Habilitar reactividad para formularios, buscadores y componentes públicos.

## **Pasos**

### **1\. Instalar Livewire**

```shell
composer require livewire/livewire
```

### **2\. Publicar configuración si aplica**

```shell
php artisan livewire:publish --config
```

### **3\. Crear componente de prueba**

```shell
php artisan make:livewire TestComponent
```

### **4\. Insertar componente en una vista Blade**

```
<livewire:test-component />
```

## **Entregables**

* Livewire instalado.  
* Componente de prueba funcional.  
* Base lista para buscador inmobiliario.

## **Validación QA**

| ID | Prueba | Resultado esperado |
| ----- | ----- | ----- |
| QA-RFC-005-01 | Renderizar componente Livewire | Componente visible |
| QA-RFC-005-02 | Interacción simple | Estado se actualiza |
| QA-RFC-005-03 | Revisar consola navegador | Sin errores JS |

## **Definition of Done**

* Livewire activo.  
* Componente base funcionando.  
* Sin conflictos con Vite/Tailwind.

---

# **7\. RFC-006 — Instalación Spatie Permission**

## **Objetivo**

Implementar roles y permisos para Owner, Admin y Agente.

## **Pasos**

### **1\. Instalar paquete**

```shell
composer require spatie/laravel-permission
```

### **2\. Publicar migraciones**

```shell
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

### **3\. Ejecutar migraciones**

```shell
php artisan migrate
```

### **4\. Agregar trait al modelo User**

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

### **5\. Crear roles base**

```shell
php artisan make:seeder PermissionSeeder
php artisan db:seed --class=PermissionSeeder
```

> Nota histórica: `RoleSeeder` puede existir como wrapper compatible, pero `PermissionSeeder` es la fuente de verdad para roles y permisos.

Roles mínimos:

* owner  
* admin  
* agente

## **Entregables**

* Roles configurados.  
* Permisos base disponibles.  
* User compatible con roles.

## **Validación QA**

| ID | Prueba | Resultado esperado |
| ----- | ----- | ----- |
| QA-RFC-006-01 | Crear roles | Roles existen |
| QA-RFC-006-02 | Asignar rol a usuario | Usuario recibe rol |
| QA-RFC-006-03 | Validar permiso por rol | Permiso aplicado correctamente |

## **Definition of Done**

* Roles creados.  
* Seeder funcional.  
* Políticas listas para usarse en módulos posteriores.

---

# **8\. RFC-007 — Instalación Media Library**

## **Objetivo**

Preparar gestión profesional de imágenes para inmuebles, agentes y proyectos.

## **Pasos**

### **1\. Instalar paquete**

```shell
composer require spatie/laravel-medialibrary
```

### **2\. Publicar migración**

```shell
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
```

### **3\. Ejecutar migración**

```shell
php artisan migrate
```

### **4\. Publicar configuración**

```shell
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-config"
```

## **Entregables**

* Tabla media creada.  
* Configuración inicial lista.  
* Base preparada para galerías de inmuebles.

## **Validación QA**

| ID | Prueba | Resultado esperado |
| ----- | ----- | ----- |
| QA-RFC-007-01 | Verificar tabla media | Tabla existe |
| QA-RFC-007-02 | Subir archivo de prueba | Archivo guardado |
| QA-RFC-007-03 | Eliminar archivo | Archivo eliminado correctamente |

## **Definition of Done**

* Media Library instalado.  
* Migraciones ejecutadas.  
* Storage validado.

---

# **9\. RFC-008 — Configuración de Ambientes**

## **Objetivo**

Definir ambientes separados para local, desarrollo, staging y producción.

## **Ambientes**

| Ambiente | Uso |
| ----- | ----- |
| Local | Desarrollo individual |
| Development | Integración técnica |
| Staging | Validación QA/UAT |
| Production | Sitio público |

## **Pasos**

### **1\. Actualizar** 

### **`.env.example`**

Debe incluir:

```
APP_NAME="New Hauz"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=newhauz
DB_USERNAME=newhauz_user
DB_PASSWORD=secure_password

FILESYSTEM_DISK=public
```

### **2\. Configurar claves**

```shell
php artisan key:generate
```

### **3\. Configurar storage**

```shell
php artisan storage:link
```

## **Entregables**

* `.env.example` actualizado.  
* Ambientes documentados.  
* Variables críticas definidas.

## **Validación QA**

| ID | Prueba | Resultado esperado |
| ----- | ----- | ----- |
| QA-RFC-008-01 | Crear ambiente desde `.env.example` | Proyecto funciona |
| QA-RFC-008-02 | Ejecutar `storage:link` | Link creado |
| QA-RFC-008-03 | Revisar variables críticas | Todas existen |

## **Definition of Done**

* Cualquier arquitecto puede clonar y levantar el proyecto.  
* Variables documentadas.  
* No hay secretos reales en repositorio.

---

# **10\. RFC-009 — Pipeline Git**

## **Objetivo**

Establecer flujo de ramas, commits, Pull Requests y control de calidad.

## **Ramas sugeridas**

| Rama | Uso |
| ----- | ----- |
| main | Producción |
| develop | Integración |
| feature/rfc-xxx-nombre | Desarrollo por RFC |
| fix/bug-xxx-nombre | Corrección |
| release/vx.x.x | Candidato a release |

## **Convención de commits**

```
feat: agrega módulo de zonas
fix: corrige validación de usuarios
docs: actualiza documentación técnica
test: agrega pruebas de permisos
refactor: mejora estructura de servicios
```

## **Flujo**

1. Crear rama desde `develop`.  
2. Desarrollar RFC.  
3. Ejecutar pruebas.  
4. Crear Pull Request.  
5. QA valida.  
6. Merge a `develop`.  
7. Release a `main`.

## **Entregables**

* Estrategia Git documentada.  
* Convención de ramas.  
* Convención de commits.  
* Política de merge.

## **Validación QA**

| ID | Prueba | Resultado esperado |
| ----- | ----- | ----- |
| QA-RFC-009-01 | Rama feature creada correctamente | Nombre válido |
| QA-RFC-009-02 | PR vinculado a RFC | Trazabilidad correcta |
| QA-RFC-009-03 | Merge sin conflictos | Integración correcta |

## **Definition of Done**

* Git Flow definido.  
* Arquitectos alineados.  
* QA sabe cómo validar versiones.

---

# **11\. RFC-010 — Docker Desarrollo**

## **Objetivo**

Crear entorno local reproducible para Kristian y Edgar.

## **Servicios mínimos**

* app  
* nginx  
* postgres  
* redis opcional  
* mailpit opcional

## **Ejemplo de** 

## **`docker-compose.yml`**

```
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

## **Pasos**

### **1\. Crear archivos Docker**

* `Dockerfile`  
* `docker-compose.yml`  
* `docker/nginx/default.conf`

### **2\. Levantar ambiente**

```shell
docker compose up -d --build
```

### **3\. Instalar dependencias dentro del contenedor**

```shell
docker compose exec app composer install
docker compose exec app npm install
```

### **4\. Ejecutar migraciones**

```shell
docker compose exec app php artisan migrate
```

## **Entregables**

* Docker funcional.  
* PostgreSQL/PostGIS operativo.  
* Laravel accesible desde navegador.

## **Validación QA**

| ID | Prueba | Resultado esperado |
| ----- | ----- | ----- |
| QA-RFC-010-01 | Ejecutar `docker compose up -d` | Servicios activos |
| QA-RFC-010-02 | Acceder a Laravel | Sitio responde |
| QA-RFC-010-03 | Ejecutar migraciones | Migraciones correctas |
| QA-RFC-010-04 | Validar PostGIS | Extensión activa |

## **Definition of Done**

* Kristian puede levantar el proyecto.  
* Edgar puede levantar el proyecto.  
* Sebastián puede validar el ambiente.  
* Documentación incluida en README.

---

# **12\. Checklist Final de la Épica 1**

| Elemento | Estado |
| ----- | ----- |
| Laravel instalado | Pendiente |
| PostgreSQL configurado | Pendiente |
| PostGIS habilitado | Pendiente |
| Filament instalado | Pendiente |
| Livewire instalado | Pendiente |
| Spatie Permission instalado | Pendiente |
| Media Library instalado | Pendiente |
| `.env.example` actualizado | Pendiente |
| Git Flow definido | Pendiente |
| Docker funcional | Pendiente |
| README técnico creado | Pendiente |
| QA validado por Sebastián | Pendiente |

---

# **13\. Criterios de Aprobación de la Épica 1**

La épica se considera terminada cuando:

1. El proyecto Laravel corre localmente.  
2. PostgreSQL está configurado.  
3. PostGIS responde correctamente.  
4. Filament carga en `/admin`.  
5. Livewire renderiza componentes.  
6. Los roles base existen.  
7. Media Library puede gestionar archivos.  
8. El ambiente puede levantarse con Docker.  
9. `.env.example` permite onboarding técnico.  
10. Sebastián valida la matriz QA de Fundación Técnica.

---

# **14\. Entregables Finales**

Al cierre de la Épica 1 deben existir:

* Repositorio funcional.  
* README técnico.  
* Docker operativo.  
* `.env.example` actualizado.  
* Panel Filament funcionando.  
* Roles base configurados.  
* PostgreSQL/PostGIS activo.  
* Evidencia QA.  
* Primer release técnico interno.

---

# **15\. Comando de Validación General**

Antes de cerrar la épica, ejecutar:

```shell
composer install
npm install
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
npm run build
php artisan test
```

Resultado esperado:

```
Todas las operaciones finalizan sin errores.
```

---

