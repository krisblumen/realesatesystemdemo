<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

# **NEW HAUZ**

Plataforma inmobiliaria integral desarrollada para la comercialización de inmuebles, arquitectura, construcción e inversión inmobiliaria en el Estado de Querétaro, México.

---

## **Descripción General**

New Hauz es una plataforma inmobiliaria administrable que centraliza:

* Comercialización de inmuebles  
* Gestión de agentes inmobiliarios  
* Captación y seguimiento de leads  
* Gestión de zonas comerciales  
* Arquitectura y construcción  
* Relación con inversionistas

El sistema está diseñado bajo una arquitectura monolítica moderna basada en Laravel, optimizada para SEO, escalabilidad y operación comercial.

---

# **Arquitectura Tecnológica**

| Componente | Tecnología |
| ----- | ----- |
| Backend | Laravel 13.x |
| Panel Administrativo | Filament v3 |
| Frontend | Blade |
| Interactividad | Livewire v3 |
| CSS | Tailwind CSS v4 |
| Base de Datos | PostgreSQL 16 |
| Geolocalización | PostGIS |
| Roles y Permisos | Spatie Permission |
| Gestión de Medios | Spatie Media Library |
| Servidor Web | Nginx |
| CDN | Cloudflare |
| Mapas | Google Maps |
| Contenedores | Docker |

---

# **Objetivos del Proyecto**

## **Objetivo Comercial**

Convertir New Hauz en una plataforma de captación para:

* Compradores  
* Arrendatarios  
* Propietarios  
* Inversionistas  
* Desarrolladores

## **Objetivo Operativo**

Centralizar la administración de:

* Usuarios  
* Agentes  
* Inmuebles  
* Zonas  
* Leads  
* Proyectos  
* Inversionistas

---

# **Cobertura Geográfica**

Fase inicial:

**Estado de Querétaro, México**

Zonas prioritarias:

* Juriquilla  
* Zibatá  
* El Campanario  
* El Refugio  
* Milenio III  
* Centro Sur  
* Cumbres del Lago  
* Corregidora  
* Huimilpan

---

# **Equipo del Proyecto**

## **Arquitectura de Solución**

### **Kristian Álvarez**

Responsable de:

* UX/UI  
* Frontend  
* Zonas  
* Inmuebles  
* SEO  
* Comercialización

---

### **Edgar**

Responsable de:

* Infraestructura  
* Seguridad  
* Usuarios  
* Leads  
* CRM  
* Integraciones

---

## **QA**

### **Sebastián**

Responsable de:

* QA Funcional  
* QA de Regresión  
* UAT  
* Evidencias  
* Aprobación de Releases

---

# **Roles del Sistema**

## **Owner**

Control total del sistema.

Puede:

* Crear usuarios  
* Suspender usuarios  
* Reactivar usuarios  
* Administrar zonas  
* Administrar inmuebles  
* Consultar todos los leads

---

## **Admin**

Puede:

* Gestionar inmuebles  
* Gestionar leads  
* Gestionar contenido

No puede:

* Crear Owners  
* Modificar permisos críticos

---

## **Agente**

Puede:

* Publicar inmuebles  
* Editar inmuebles propios  
* Consultar leads propios

No puede:

* Administrar usuarios  
* Consultar leads ajenos

---

# **Roadmap del Proyecto**

## **RFC-000**

### **Gobierno del Proyecto**

* Project Constitution  
* Coding Standards  
* AI Development Playbook

---

# **Épica 1 — Fundación Técnica**

RFC-001 a RFC-010

* Laravel 13  
* PostgreSQL  
* PostGIS  
* Filament  
* Livewire  
* Spatie Permission  
* Media Library  
* Ambientes  
* Git Flow  
* Docker

---

# **Épica 2 — MVP**

RFC-011 a RFC-037

Incluye:

* Usuarios  
* Roles  
* Zonas  
* Inmuebles  
* Leads  
* Frontend público  
* Buscador  
* Ficha de inmueble

---

# **Épica 3 — Comercialización**

RFC-038 a RFC-047

Incluye:

* Dashboard comercial  
* SEO avanzado  
* Tracking WhatsApp  
* Filtros avanzados  
* Google Maps

---

# **Épica 4 — Escalamiento**

RFC-048 a RFC-058

Incluye:

* CRM  
* Reportes  
* Portal propietarios  
* IA  
* Integraciones externas

---

# **Estado Actual del Proyecto**

## **Épica 1 — Fundación Técnica**

| RFC | Descripción | Estado |
| ----- | ----- | ----- |
| RFC-001 | Laravel 13 | ⬜ |
| RFC-002 | PostgreSQL | ⬜ |
| RFC-003 | PostGIS | ⬜ |
| RFC-004 | Filament | ⬜ |
| RFC-005 | Livewire | ⬜ |
| RFC-006 | Spatie Permission | ⬜ |
| RFC-007 | Media Library | ⬜ |
| RFC-008 | Ambientes | ⬜ |
| RFC-009 | Git Flow | ⬜ |
| RFC-010 | Docker | ⬜ |

---

# **Convención de Ramas**

```
main
develop

feature/rfc-xxx-descripcion
fix/bug-xxx-descripcion
release/vx.x.x
```

---

# **Convención de Commits**

```
feat:
fix:
docs:
refactor:
test:
chore:
```

Ejemplos:

```
feat: agrega gestión de zonas comerciales
fix: corrige validación de usuarios suspendidos
docs: actualiza README
test: agrega pruebas de permisos
```

---

# **Flujo de Desarrollo**

1. Crear rama desde `develop`  
2. Implementar RFC  
3. Ejecutar pruebas locales  
4. Crear Pull Request  
5. Validación QA  
6. Merge a `develop`  
7. Release a `main`

---

# **Estructura Documental**

```
docs/
│
├── architecture/
│
├── rfc/
│
├── qa/
│
├── ux/
│
└── deployment/
```

---

# **Requisitos Locales**

* PHP 8.3+  
* Composer 2.x  
* Node.js 22+  
* PostgreSQL 16  
* Docker  
* Git

---

# **Criterios de Calidad**

Todo RFC debe cumplir:

* Código funcional  
* Validaciones implementadas  
* Permisos correctos  
* Pruebas QA aprobadas  
* Sin errores críticos  
* Sin regresiones  
* Documentación actualizada

---

# **Definition of Done**

Un RFC se considera terminado cuando:

* Desarrollo concluido  
* QA aprobado  
* Regresión aprobada  
* Pull Request aceptado  
* Merge realizado a develop  
* Documentación actualizada

---

# **Licencia**

Proyecto privado propiedad de New Hauz.

Todos los derechos reservados.

---

**Versión:** 0.1.0  
**Estado:** En Construcción  
**Arquitectura:** Monolítica Laravel \+ Filament \+ PostgreSQL \+ PostGIS \+ Livewire

