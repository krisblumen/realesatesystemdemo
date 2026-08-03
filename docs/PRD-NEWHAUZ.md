**DOCUMENTO DE TRABAJO PARA EQUIPO**

**Reestructura del sitio New Hauz \+ Plataforma Inmobiliaria Administrable**

Base técnica: Laravel \+ Filament \+ Sanctum | Frontend: Next.js \+ React | Base de datos: PostgreSQL

*Versión de trabajo para coordinación de diseño, desarrollo, contenido y operación comercial*

 

| Documento | Uso interno | Decisión clave |
| :---- | :---- | :---- |
| Guía operativa de ejecución | Alinear al equipo de diseño, desarrollo, contenido y administración sobre la nueva estructura del sitio. | Integrar formalmente el área de comercialización inmobiliaria dentro del ecosistema New Hauz. |

 

# **1\. Objetivo del documento**

**Este documento traduce la propuesta técnica en una guía de ejecución para el equipo de trabajo.** Su finalidad es establecer la arquitectura ideal del sitio New Hauz, los módulos funcionales, las responsabilidades del equipo, los entregables por fase y los criterios mínimos para que el proyecto pueda construirse con orden, enfoque comercial y escalabilidad.

·       Consolidar New Hauz como un ecosistema de arquitectura, construcción, comercialización inmobiliaria e inversión en Querétaro.  
·       Definir la nueva estructura del sitio y eliminar secciones que ya no forman parte del alcance.  
·       Alinear frontend, backend, contenido, SEO, diseño UI y operación comercial bajo una misma visión.  
·       Preparar una plataforma administrable para agentes, zonas, inmuebles, fotografías, precios, leads y estatus comerciales.

# **2\. Base técnica considerada**

| Componente | Definición para el proyecto |
| :---- | :---- |
| Backend/API | Laravel como núcleo de lógica de negocio, autenticación, permisos, administración y API. |
| Panel administrativo | Filament para módulos CRUD, dashboards, formularios, tablas y operación interna. |
| Permisos | Spatie Laravel Permission para roles de owner, administradores y agentes. |
| Autenticación API | Laravel Sanctum para proteger endpoints consumidos por Next.js. |
| Frontend público | Next.js \+ React para SEO, rendimiento, rutas dinámicas y experiencia responsive. |
| Base de datos | PostgreSQL como base principal, preparada para crecimiento, relaciones complejas y PostGIS. |
| Mapas | Google Maps o Mapbox para ubicación de inmuebles, zonas y futuras búsquedas geográficas. |
| Storage | Storage local inicial o S3 compatible / Cloudflare R2 para fotografías y documentos. |

 

# **3\. Posicionamiento estratégico del sitio**

**Mensaje rector recomendado:** New Hauz integra arquitectura, construcción, comercialización inmobiliaria e inversión en proyectos de alto valor en Querétaro.

| Línea de negocio | Función comercial | Secciones relacionadas |
| :---- | :---- | :---- |
| Arquitectura | Diseño, conceptualización, proyecto ejecutivo y visualización. | Servicios, Proyectos, Partners |
| Construcción | Ejecución residencial, comercial y fraccionamientos. | Servicios, Proyectos |
| Inmobiliaria | Compra, venta, renta, preventas y comercialización de desarrollos. | Inmobiliaria, Propiedades, Agentes |
| Inversionistas | Presentación del modelo de inversión y captación de interesados calificados. | Inversionistas, Proyectos disponibles, Contacto |

 

# **4\. Menú principal aprobado**

El menú debe quedar concentrado, sin Blog/Guías y sin secciones que no estén en el alcance comercial actual. *Menos ruido, más conversión: que el usuario no necesite un mapa del tesoro para encontrar el botón de contacto.*

| Orden | Opción de menú | Objetivo |
| :---- | :---- | :---- |
| 1 | Inicio | Comunicar la propuesta integral y dirigir a los principales embudos. |
| 2 | Nosotros | Presentar la empresa, equipo, proceso, experiencia y confianza. |
| 3 | Servicios | Mostrar capacidades de arquitectura, construcción, renders, marketing e interiorismo. |
| 4 | Proyectos | Exhibir desarrollos, casas, plazas y galería de trabajos. |
| 5 | Inmobiliaria | Captar compradores, propietarios, arrendadores, desarrolladores y leads inmobiliarios. |
| 6 | Inversionistas | Explicar modelo de inversión, proyectos disponibles y solicitud de información. |
| 7 | Partners | Mostrar alianzas actuales: A74 Arquitectura y Maracuyá Collective. |
| 8 | Contacto | Centralizar formularios, WhatsApp, correo, ubicación y horarios. |

 

# **5\. Arquitectura de información final**

| Sección | Contenido interno |
| :---- | :---- |
| Inicio | • Hero principal• Qué hacemos• Servicios destacados• Proyectos destacados• Propiedades disponibles• Modelo para inversionistas• Partners• Testimonios• CTA final |
| Nosotros | • Quiénes somos• Nuestra visión• Equipo• Proceso New Hauz• Experiencia• FAQ |
| Servicios | • Diseño Arquitectónico• Construcción Residencial• Construcción Comercial• Fraccionamientos• Supervisión DRO• Renders Arquitectónicos• Marketing Inmobiliario• Interiorismo / Cocinas / Closets |
| Proyectos | • Desarrollos Residenciales• Casas de Diseño• Plazas Comerciales• Proyectos en Venta• Proyectos Vendidos• Galería |
| Inmobiliaria | • Comprar• Rentar• Vender mi Propiedad• Valuación Comercial• Preventas• Comercialización de Desarrollos• Agentes por Zona• Servicios Inmobiliarios |
| Inversionistas | • Modelo de Inversión• Proyectos Disponibles• Cómo Funciona• Preguntas Frecuentes• Solicitar Información |
| Partners | • A74 Arquitectura• Maracuyá Collective |
| Contacto | • Formulario general• WhatsApp• Correo• Dirección• Horarios |

 

# **6\. Cambios de alcance aprobados**

| Área | Acción | Resultado |
| :---- | :---- | :---- |
| Partners | Actualizar | Salen partners anteriores. Entran A74 Arquitectura y Maracuyá Collective. |
| Servicios | Eliminar | Desaparece Branding para Franquicias. |
| Inversionistas | Eliminar | Desaparece Rendimientos Históricos. |
| Menú principal | Eliminar | Desaparece Blog / Guías. |

 

# **7\. Área Inmobiliaria: estructura comercial**

**La sección Inmobiliaria debe funcionar como unidad de negocio visible, no como un simple catálogo de propiedades.** Debe captar compradores, propietarios, inversionistas y desarrolladores que buscan comercializar proyectos.

| Subsección | Objetivo | CTA principal |
| :---- | :---- | :---- |
| Comprar | Mostrar catálogo de inmuebles en venta con filtros por zona, precio, tipo y características. | Ver propiedades |
| Rentar | Mostrar propiedades disponibles en renta y captar prospectos calificados. | Ver rentas disponibles |
| Vender mi Propiedad | Captar inventario de propietarios que desean vender. | Solicitar valuación comercial |
| Valuación Comercial | Solicitar datos del inmueble para estimar precio de salida al mercado. | Quiero valuar mi propiedad |
| Preventas | Mostrar desarrollos o propiedades en etapa de preventa. | Solicitar información |
| Comercialización de Desarrollos | Ofrecer estrategia comercial para fraccionamientos, condominios, plazas o proyectos verticales. | Comercializar mi desarrollo |
| Agentes por Zona | Presentar asesores responsables por zona comercial dentro de Querétaro. | Contactar asesor |
| Servicios Inmobiliarios | Explicar servicios de promoción, filtro de prospectos, negociación, cierre y acompañamiento. | Hablar con un asesor |

 

# **8\. Ficha de inmueble: requerimientos mínimos**

| Bloque | Contenido requerido |
| :---- | :---- |
| Galería | Imagen principal, galería secundaria, video o recorrido virtual opcional. |
| Datos comerciales | Título, precio, tipo de operación, tipo de inmueble, estatus y ubicación. |
| Características | Terreno, construcción, recámaras, baños, estacionamientos, niveles, antigüedad y amenidades. |
| Descripción | Copy comercial claro, sin exageraciones y optimizado para SEO local. |
| Ubicación | Zona, municipio, colonia/desarrollo y mapa con ubicación aproximada. |
| Agente | Nombre, foto, WhatsApp, teléfono, correo y zona asignada. |
| Conversión | Formulario de contacto, botón de WhatsApp y propiedades similares. |
| SEO | Slug amigable, metatítulo, metadescripción, alt text de imágenes y datos estructurados cuando aplique. |

 

# **9\. Backend administrativo: módulos de operación**

| Módulo | Funciones principales | Prioridad |
| :---- | :---- | :---- |
| Usuarios y agentes | Alta, edición, suspensión, reactivación, perfil, roles y asignación por zona. | MVP |
| Zonas comerciales | Catálogo de zonas, relación con municipios, colonias/desarrollos y responsables. | MVP |
| Inmuebles | Alta, edición, fotografías, características, precio, estatus, agente y zona. | MVP |
| Leads | Captura de prospectos desde formularios, relación con inmueble/agente y seguimiento básico. | MVP |
| Propiedades destacadas | Marcar inmuebles para Home, preventas o secciones comerciales. | Fase 2 |
| Dashboard comercial | Indicadores por agente, zona, inmueble, estatus y fuente de lead. | Fase 2 |
| Reportes | Exportación a Excel y métricas de operación. | Fase 3 |
| Integraciones | CRM, portales externos, automatizaciones e IA para descripciones. | Fase 3 |

 

# **10\. Roles del sistema y reglas críticas**

| Rol | Permisos clave |
| :---- | :---- |
| Usuario Principal / Owner | Control total. Puede crear, suspender y reactivar usuarios; administrar zonas; publicar, pausar o suspender inmuebles; ver todos los leads. |
| Administrador interno | Gestiona inmuebles, revisa publicaciones, administra leads y apoya operación; no modifica permisos críticos ni al owner. |
| Agente inmobiliario | Publica, edita y pausa sus propios inmuebles. Consulta leads generados por sus propiedades. |
| Usuario suspendido | No puede iniciar sesión ni publicar. Sus inmuebles se ocultan, pausan o pasan a revisión según política comercial. |

 

**Regla crítica de negocio:** solo el Usuario Principal puede dar de alta, suspender o reactivar usuarios/agentes.

# **11\. Responsabilidades por equipo**

| Equipo / Rol | Responsabilidades |
| :---- | :---- |
| Dirección del proyecto | Validar alcance, prioridades, estructura del sitio, contenido institucional, criterios comerciales y entregables finales. |
| UX/UI | Diseñar wireframes, componentes, ficha de inmueble, buscador, filtros, landing de inmobiliaria y experiencia responsive. |
| Frontend | Implementar Next.js, rutas públicas, buscador, filtros, fichas de inmueble, formularios, SEO técnico y rendimiento. |
| Backend | Construir Laravel API, modelos, migraciones, políticas, permisos, endpoints y lógica de estados. |
| Admin/Filament | Configurar panel administrativo, CRUDs, dashboards, validaciones, filtros, tablas y gestión de imágenes. |
| Contenido/Copy | Redactar textos por sección, fichas de servicios, copies inmobiliarios, CTAs y metadatos SEO. |
| Comercial/Inmobiliaria | Definir zonas, agentes, tipos de inmuebles, flujo de leads, inventario inicial y reglas de publicación. |
| QA | Validar flujos, permisos, responsive, formularios, estados de propiedades, rendimiento y consistencia visual. |

 

# **12\. Plan de implementación por fases**

| Fase | Objetivo | Entregables principales |
| :---- | :---- | :---- |
| Fase 1 \- MVP | Lanzar operación funcional. | Login, owner, agentes, zonas, inmuebles, fotografías, frontend público, buscador básico, detalle de inmueble, formulario y leads. |
| Fase 2 \- Comercialización | Mejorar captación y conversión. | SEO avanzado, inmuebles destacados, mapa, filtros avanzados, dashboard de leads, medición de clics a WhatsApp y notificaciones. |
| Fase 3 \- Escalamiento | Convertir la plataforma en activo comercial integral. | CRM básico, reportes por agente/zona, portal para propietarios, integraciones con portales externos e IA para descripciones. |

 

# **13\. Criterios de aceptación del MVP**

·       El sitio público muestra correctamente Inicio, Nosotros, Servicios, Proyectos, Inmobiliaria, Inversionistas, Partners y Contacto.  
·       El menú no incluye Blog/Guías ni Branding para Franquicias.  
·       Partners muestra únicamente A74 Arquitectura y Maracuyá Collective.  
·       Inversionistas no incluye Rendimientos Históricos.  
·       El owner puede crear, suspender y reactivar agentes; ningún otro rol puede hacerlo.  
·       Cada inmueble puede registrarse con fotografías, precio, características, zona, agente y estatus comercial.  
·       El frontend permite buscar y filtrar inmuebles por operación, tipo, zona y precio como mínimo.  
·       La ficha de inmueble tiene galería, precio, características, agente responsable y formulario de contacto.  
·       Los leads se guardan en el backend y quedan relacionados con inmueble y agente.  
·       El sitio funciona correctamente en móvil, tablet y escritorio.  
·       La base de datos de producción usa PostgreSQL.

# **14\. Checklist de contenido requerido**

| Sección | Contenido a preparar |
| :---- | :---- |
| Inicio | Hero, propuesta de valor, servicios destacados, proyectos, propiedades, inversionistas, partners y CTA final. |
| Nosotros | Historia, visión, equipo, experiencia, proceso y preguntas frecuentes. |
| Servicios | Texto comercial por servicio, imágenes, beneficios y CTA. |
| Proyectos | Nombre, ubicación, descripción, galería, alcance, estatus y ficha resumida. |
| Inmobiliaria | Textos para comprar, rentar, vender, valuación, preventas, desarrollos y agentes por zona. |
| Inversionistas | Modelo, proyectos disponibles, cómo funciona, FAQ y formulario de solicitud. |
| Partners | Descripción de A74 Arquitectura y Maracuyá Collective, relación con New Hauz y valor agregado. |
| Contacto | WhatsApp, correo, dirección, horarios, formulario y mapa. |

 

# **15\. Riesgos, decisiones pendientes y recomendaciones**

| Tema | Riesgo / decisión | Recomendación |
| :---- | :---- | :---- |
| Datos de inversión | Comunicar rendimientos puede generar fricción legal o expectativa financiera. | Mantener información descriptiva del modelo y llevar al usuario a asesoría personalizada. |
| Inventario inicial | Lanzar sin propiedades suficientes reduce percepción comercial. | Cargar inventario mínimo validado antes de publicar la sección Inmobiliaria. |
| Fotografías | Imágenes de baja calidad bajan conversión. | Definir estándar visual para portada, galería y dimensiones. |
| Zonas | Zonas mal definidas generan asignaciones confusas. | Aprobar catálogo comercial de zonas antes de programar reglas. |
| Permisos | Roles ambiguos pueden abrir huecos operativos. | Documentar matriz de permisos y probarla con QA. |
| SEO local | Sin URLs y metadatos correctos se pierde oportunidad orgánica. | Implementar slugs, metadatos y estructura SEO desde MVP. |

 

# **16\. Próximos pasos para kickoff**

·       Aprobar arquitectura de información final y menú principal.  
·       Definir catálogo inicial de zonas comerciales de Querétaro.  
·       Definir tipos de inmueble, tipos de operación y estatus comerciales.  
·       Preparar contenido institucional y textos de servicios.  
·       Levantar inventario inicial de propiedades y proyectos.  
·       Diseñar wireframes de Home, Inmobiliaria, listado y ficha de inmueble.  
·       Definir modelo de datos final y crear migraciones en PostgreSQL.  
·       Configurar roles, permisos y flujos de publicación en Filament.  
·       Implementar frontend público y formularios de leads.  
·       Ejecutar QA funcional, responsive y de permisos antes de producción.

