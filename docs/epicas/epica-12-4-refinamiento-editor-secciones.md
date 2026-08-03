# Épica 12.4 — Refinamiento del editor de secciones: fiabilidad, medios, tipografía y marca

**Periodo:** 2026-07-27 a 2026-07-29. **44 commits** entre `40c0d5c` y `c00640c`.
**Punto de partida:** Épica 12.3 cerrada y con gate de implementación aprobado
(`780ac2c`). El editor de secciones de la Épica 12.2 estaba en producción, con
sus cinco lotes auditados y el editor JSON retirado.

## 0. Qué es este documento y qué no es

No es un diseño previo a implementación como `epica-12-1-mejora-ux-hero.md` o
`epica-12-3-media-servicios-diseno.md`: no hubo auditoría externa previa ni
gate formal. Es una bitácora **as-built**, en el mismo espíritu que la §19 del
documento principal — se escribe después de construir, verificar y publicar
cada pieza, no antes.

El origen tampoco es una auditoría: es el **owner del sitio usando el CMS de
verdad**, sobre datos reales, en su propia página en producción. Cada punto de
esta épica nació de una de dos formas:

1. El owner navegó el panel o el sitio publicado y encontró que algo no se
   comportaba como esperaba (un botón que no respondía, una foto que no se
   podía cambiar, una sección que no se parecía a la página real).
2. El owner pidió una capacidad nueva del editor (elegir un color, una
   tipografía, mover el orden de las secciones) porque necesitaba resolver
   algo concreto del sitio, no como ejercicio de diseño.

Eso cambia el criterio de qué se documenta: no hay ítems de auditoría (`C-n`,
`M-n`) que cerrar. Hay **defectos reproducidos y verificados en el navegador**
antes de tocar código, y **decisiones de producto** tomadas por el owner y
ejecutadas con las mismas garantías normativas que ya regían la épica (§16 del
documento principal): nada del payload se interpola en una clase o en un
`style` (§16.1.1), las paletas de color y tipografía son listas **cerradas**
que el schema valida, y el sitio se sigue sirviendo sin `unsafe-inline`.

**Regla de verificación seguida en todo el periodo:** ningún cambio se dio por
bueno por "se ve bien en el código". Cada uno se reprodujo primero (cuando era
un defecto) y se verificó después en el navegador con medidas reales —
`getComputedStyle`, anchos en píxeles, listas de clases — no a ojo. Donde el
navegador no alcanzaba (verificar que un color elegido en el panel es el mismo
que ve el visitante, por ejemplo), la prueba compara el cálculo en PHP contra
el cálculo en CSS.

## 1. Fiabilidad del editor: lo que el gate de 12.2 no había probado

El gate de la Épica 12.2 verificó que los formularios **guardaban** correcto.
Nadie había probado sistemáticamente que lo guardado **volviera a cargar**
igual al reabrir el editor. La brecha era real y grave.

### 1.1 Los repeaters no cargaban lo guardado (`40c0d5c`)

Cinco tipos de sección — `values`, `metrics`, `partners`, `feature_sequence`,
`capability_cards` — declaraban un `Repeater` sobre el **mismo** state path
`payload.items`. `SectionsRelationManager::form()` declaraba los campos de
**todos** los tipos a la vez y los ocultaba con `visible()`; con varios
repeaters apuntando al mismo path, Livewire los pisaba entre sí. El síntoma:
el owner abría "Cifras", veía los campos en blanco, y si guardaba sin darse
cuenta **borraba el contenido real**, porque el estado que sí existía —el ítem
entero bajo la clave `name` de `partners`— no coincidía con lo que el
formulario de `metrics` esperaba leer.

Pasó el gate porque la matriz de tests de 12.2 probaba el guardado, y ningún
test abría el editor para comprobar que los datos **vuelven**. Se corrigió en
dos frentes:

- `fieldsForMountedType()` arma el formulario **sólo** con los campos del tipo
  de la sección que está montada, nunca los ocho tipos a la vez.
- `partners` dejó de usar un `Repeater::simple()` — que hidrata desde una
  lista plana de strings mientras su schema guarda objetos `{name, media_id,
  alt}` — para tener la misma forma en los dos sentidos.

Se agregó una clase de test (`FrontendSectionHydrationTest`, ampliada durante
todo el periodo) dedicada exclusivamente al ciclo abrir → editar → guardar →
reabrir, para cada tipo con repeater. Es la clase que en las semanas
siguientes atrapó cada regresión de hidratación antes de publicarse.

### 1.2 El botón "Guardar cambios" no respondía (`23d13a1`)

Reproducido en vivo: con un archivo de 11.6 MB en el campo de foto,
`form.checkValidity()` daba `false` y el único campo inválido era el input de
FilePond. El límite de subida estaba en 5 MB; una foto de teléfono actual lo
supera sin esfuerzo. Cuando eso pasaba, **el navegador bloqueaba el envío del
formulario entero por validación nativa HTML5**, sin disparar el evento
`submit` y sin un solo request al servidor. El mensaje de error de FilePond
queda anclado a un input oculto, así que en pantalla no aparecía nada — para
el agente, el botón simplemente no hacía nada, y además perdía cualquier
cambio de texto que hubiera hecho en los demás campos.

Arreglo de dos partes que **tienen que ir juntas**: el límite sube a 12 MB
**y** la foto se redimensiona en el navegador antes de subir. Subir el límite
solo habría mudado el fallo del navegador al servidor — PHP corta en
`upload_max_filesize` (10 MB en este entorno) y el archivo original habría
viajado igual. Verificado de punta a punta: una foto de 11.6 MB terminó en
disco pesando 1.7 MB.

El mismo límite y el mismo defecto estaban en `Project`; se corrigió en el
mismo cambio. El hero del sitio arrastraba el desfase **inverso** desde que se
le subió el límite a 12 MB sin redimensionado: aceptaba en el navegador más de
lo que PHP dejaba pasar, así que entre 10 y 12 MB el archivo entraba al
formulario y moría en el servidor. Se corrigió con el mismo mecanismo.

### 1.3 Fotos de un inmueble publicado: guardado silencioso (`c158344`)

Reproducido con la pista exacta del owner: pausar y cambiar fotos funcionaba,
publicado y cambiar fotos no. La causa era un desencuentro de **orden**, no
una regla de más. `Property` tiene un candado que impide que un inmueble
**publicado** se quede sin foto de portada, y ese candado cuenta reemplazos
mirando la base de datos: sólo tolera "primero agrego la nueva, después borro
la vieja". El formulario de Filament hace lo contrario — borra y luego guarda
— así que en el instante intermedio la base no tenía ninguna foto, aunque el
agente sí había elegido una nueva, y el candado cortaba el guardado entero.

Era invisible porque el error de validación viajaba con la clave `cover`, y
los campos del formulario de Filament viven bajo `data.*`: no había dónde
mostrarlo. El agente perdía además cualquier texto que hubiera cambiado en el
mismo guardado.

Un modelo no puede distinguir "me están reemplazando" de "me están borrando":
el reemplazo todavía no existe como media en ese instante. Quien sí lo sabe es
la pantalla, que tiene el archivo nuevo en la mano. La solución acota el
candado a una ventana explícita —
`Property::deferCoverGuard(callable): void` / `isCoverGuardDeferred(): bool`,
con `finally` — que sólo usa `EditProperty::save()`, y durante esa ventana la
regla la aplica la pantalla sobre lo que el agente eligió, con el error puesto
en el campo de la foto (`data.cover`), donde se ve.

Fuera de esa pantalla el candado sigue entero:
`Media::deleting` sobre la portada de un inmueble publicado sigue fallando
—es lo que ya exigía `PropertyPublicationTest`, y un primer intento de arreglo
que sacaba el candado del modelo entero rompió ese test—. La ventana de
excepción tiene dos pruebas propias: que el candado vuelve a su lugar después
de un guardado exitoso, y que también vuelve si el guardado explota a mitad de
camino.

### 1.4 El editor de imágenes forzaba recorte cuadrado (`11cd4ba`)

Regresión propia del arreglo anterior (§1.2): el redimensionado a 1920×1920
antes de subir hizo que Filament **derivara** la proporción del recorte de
esas mismas medidas — 1920/1920 es 1:1, así que el editor quedaba cuadrado sin
importar desde qué tirador se arrastrara. No hay forma de tener las dos cosas:
con ambas dimensiones de resize puestas, Filament ignora cualquier viewport
explícito que se declare.

Se sacrificó el resize automático (queda el recorte libre, que es lo que el
agente usa a diario) y el límite bajó de 12 a 8 MB para quedar **por debajo**
de `upload_max_filesize` — así todo lo que el formulario acepta se puede subir
de verdad. El editor ganó además proporciones fijas seleccionables (Libre,
16:9, 4:3, 3:2, 1:1), así que lograr un recorte 16:9 exacto para una portada
ya no depende del pulso.

### 1.5 Publicar no se reflejaba sin recargar (`cd70e0d`)

El botón "Publicar cambios" no fallaba en pantalla: fallaba en silencio contra
el guard de concurrencia. Mandaba la `draft_revision` que la pantalla había
capturado **al abrirse**, y guardar una sección hace avanzar esa revisión — el
publisher rechazaba la publicación por "el contenido cambió desde que
abriste la página", que es exactamente la protección correcta contra que
**otra sesión** te pise, sólo que estaba disparándose contra la propia sesión
del agente.

El guardado de una sección ahora emite un evento de Livewire que la pantalla
escucha para refrescar su copia de la revisión, sin recargar ni sacar al
owner del lugar donde está trabajando. La protección real —otra sesión
editando el mismo borrador— sigue frenando la publicación, con test propio
que lo verifica sin tocar el mecanismo nuevo.

### 1.6 La copia a disco público dependía de un worker inexistente (`a09266c`)

El owner publicaba fotos del hero, el panel decía "Publicada", el sitio no las
mostraba. La promoción de media (disco privado → público) corría en cola, y
no había ningún worker de cola vivo en ese momento — le pasó dos veces
seguidas. La red de rescate (reconciliación) tampoco ayudaba porque corre por
el scheduler, que también necesita un proceso corriendo.

La promoción pasa a ser **síncrona** en los tres puntos que la invocan (costo
medido: 11–44 ms por imagen). Lo que **no** cambió: la copia sigue ocurriendo
fuera de la transacción de base de datos —el sistema de archivos no participa
de un rollback—, y un fallo de copia no tumba una publicación ya confirmada;
la media queda `pending` y la reconciliación la retoma, también de forma
síncrona ahora. Es una desviación declarada del diseño original de la Épica
12.1 (que preveía cola asíncrona); queda registrada como tal en el documento
principal.

## 2. Título de la página en edición (`0e88fa1`)

Las cinco páginas comparten la misma pantalla de edición, y el encabezado
salía siempre "Editar Página Del Sitio" — sin decir cuál. El nombre visible
("Editar Página Del Sitio: Inicio") sale del **mismo** allowlist que ya
nombraba los enlaces del sitio público (`PublicRoutes`), no de una lista
nueva: una segunda lista habría terminado llamando distinto a la misma página
en el panel y en el menú. Alimenta también las migas de pan, el buscador y la
pestaña del navegador.

## 3. Catálogo de propiedades: botones de acceso y filtro de oportunidades

### 3.1 Botón al catálogo desde `featured_properties` (`d89f7f9`)

Arriba a la derecha, a la altura del título, en el color principal
tematizable. **El destino no se pregunta**: "el catálogo" es una sola página
del sitio, así que ofrecer elegirlo sería ofrecer equivocarse en algo sin
alternativa real. El owner elige sólo el texto del botón; el compilador arma
el CTA completo y un `target` mandado a mano en el POST se ignora — con test
que lo comprueba.

Un guard existente (`FrontendDynamicSectionEditorTest`) frenó el primer
intento: las secciones dinámicas sólo pueden exponer parámetros de
**presentación**. El texto lo es y entró en la allowlist; el guard salió
**más estricto**, no más débil — ahora también verifica que nunca aparezca un
campo de destino en el formulario.

### 3.2 `opportunity_properties`: texto y botón filtrado (`e52fbd7`, `17c44ce`)

El botón lleva al catálogo **filtrado** (`?oportunidad=1`), en color de
acento. El filtro viaja dentro del objeto CTA — igual que el `label` — y lo
aplica el `CtaResolver` a través de una allowlist cerrada de query params
(`private static function filtro()`), por el mismo motivo que el destino: un
parámetro libre en la URL es texto del owner llegando a una consulta. Se
descartó agregar una clave al allowlist de rutas públicas porque también la
enumeran el sitemap (donde una URL filtrada sería contenido duplicado) y el
selector de enlaces del menú.

El backend que interpreta `?oportunidad=1`
(`PropertyController::index` filtra por `is_opportunity` y lo agrega a la
lista de `filters` de la vista) se agregó en el mismo cambio; sin él, el botón
del home habría llevado a un catálogo sin filtrar.

Faltaba que el visitante pudiera pedir el mismo filtro por su cuenta desde el
catálogo (`17c44ce`): un checkbox propio — no una opción del selector de
precio, que habría mezclado "sí/no" con una lista de rangos y habría borrado
cualquier rango puesto al marcarse — que **reemplaza** al campo oculto que
antes llevaba el filtro entre búsquedas (con los dos coexistiendo, destildar
la casilla nunca habría quitado nada: el campo oculto seguía mandando el
filtro).

## 4. "Qué hacemos" (`capability_cards`): del listado de servicios a contenido propio

### 4.1 La sección equivocada en el home (`40c0d5c`)

La home mostraba el listado dinámico de servicios (el mismo partial que
`/servicios`) donde el sitio publicado tiene una sección de contenido libre
—antetítulo, frase, texto y hasta ocho tarjetas—. El tipo `capability_cards`
existía en el schema pero la migración no había sembrado su contenido; la
migración corregida siembra el texto que el sitio ya publica, así el owner
edita en vez de redactar de cero. Queda advertido en el propio documento que
los mismos textos ahora existen en dos pantallas (`/servicios` y esta
sección) y pueden desincronizarse si se edita sólo una.

### 4.2 Íconos, hover, alineación, borde (`f01b41f`, `fce30a9`, `1f6e8f3`, `3a87d17`, `e2a27f3`)

Las tarjetas pasaron a llevar ícono, título y texto en formato vertical, con
sombra sutil y un desplazamiento de 4 px al pasar el mouse — medido en el
navegador, no estimado (`translate: 0px -4px`, sombra de `0.06/2px 8px` a
`0.12/16px 40px` sólo bajo el cursor). Es el mismo gesto que ya usa el resto
del sitio.

Los íconos viven en `config('frontend-sections.card_icons')`, que alimenta a
la vez el selector del formulario y el render — agregar uno es tocar un solo
archivo. Lo que viaja en el payload es la **clave** del ícono, nunca su
`path`: dibujar un `<path>` con texto del owner sería inyección de SVG, y el
schema rechaza cualquier clave fuera de la lista. El catálogo creció de 8 a
16 íconos para cubrir el vocabulario de una inmobiliaria que también
construye (obra, supervisión de obra, documentación, financiamiento,
certificación, plusvalía, etc.); hay un test que verifica que ningún par de
claves comparta el mismo `path` — dos nombres para el mismo dibujo.

El encabezado (antetítulo, frase, texto) se puede alinear izquierda/centro,
con centro como default porque es como se ve el sitio publicado hoy: una
sección sin elección explícita no debe cambiar de aspecto sólo por haberse
guardado. **La alineación no mueve las tarjetas**, que son bloques con
composición propia — un test lo protege.

Las tarjetas ganaron un borde opcional (toggle + grosor de 1 a 4px, enum
cerrado porque el render lo mapea a una clase Tailwind literal) cuyo **color**
sale de una paleta cerrada elegible con selector visual — la primera versión
de esa paleta, con diez muestras derivadas de los dos colores de marca. Las
muestras se calculan en PHP con la misma mezcla lineal en sRGB que hace
`color-mix()` en el CSS del sitio, y un test compara los dos cálculos byte a
byte: si divergieran, el owner elegiría un color en el panel y vería otro
publicado.

## 5. El bloque de cierre (`cta`): partición, color, WhatsApp, resplandor

### 5.1 Datos destacados (`28928d4`)

`cta` cierra el home y otras cuatro páginas más. Ganó una columna derecha
opcional con hasta cinco datos (`{value, text}`, como el "+150" que ya existe
en producción). Sin datos, el bloque sigue centrado y a todo el ancho, igual
que siempre — es lo que exigía no tocar los otros cuatro cierres. **Los datos
no estiran la tarjeta**: a partir del cuarto, tipografía y espaciado bajan de
escala en vez de empujar la altura hacia abajo (medido: 475px con tres datos,
453 con cuatro, 450 con cinco). La columna de cada dato se alinea con CSS
subgrid — un ancho mínimo fijo dejaba "+12%" empujando su propio texto contra
"+9" arrancando en otra x.

### 5.2 Color de fondo con degradado (`d5ab078`)

Se elige con las mismas muestras que el borde de "Qué hacemos" — es el
nacimiento de `brand_palette` como lista **única** compartida entre usos, en
vez de una paleta por consumidor. Sobre el color elegido va un degradado de
negro casi transparente hacia abajo-derecha; un solo par de clases sirve para
las diez opciones porque el degradado es el mismo tono ensombrecido, no un
segundo color por muestra.

**La tinta se invierte sola sobre fondos claros**, calculado por luminancia
WCAG (umbral 4.5:1) sobre el color real del cliente — no una lista escrita a
mano, que quedaría mintiendo en cuanto el owner cambiara su marca. `CardBorderPalette`
se renombró a `BrandPalette` y salió del namespace `Media\`, donde nunca tuvo
sentido.

### 5.3 CTA de WhatsApp (`c60bc38`)

Botón verde con el logo de WhatsApp cuando el destino resuelve a ese canal. El
`type` del CTA tiene que **viajar** hasta el componente `cta-button` —el único
punto por donde pasan todos los botones del sitio— porque una vez resuelto el
destino a URL plana, que fuera WhatsApp ya no se podía deducir sin rehacer
afuera una decisión que el resolver ya había tomado.

El texto **no** es blanco fijo: un guard existente (§16.5, prohibición de
`text-white` en botones) lo frenó, con razón — blanco sobre el verde de
WhatsApp da 1.98:1, muy por debajo del 4.5:1 exigible. Se resolvió con roles
semánticos propios (`bg-whatsapp` / `text-on-whatsapp`) sobre un verde más
oscuro que sí da 7.5:1. WhatsApp y su verde son **los únicos** colores del
sistema que no siguen al tema del cliente, a propósito: no son un tono de
marca, son la firma reconocible de un canal ajeno.

### 5.4 Resplandores (`98ff03a`, `e9f2ae9`)

Los botones (principal en acento, WhatsApp en verde) ganaron una sombra de dos
capas: halo sin desplazamiento + sombra de despegue. De paso se corrigió un
defecto latente — `--shadow-cta` tenía el ámbar clavado en `rgba()`, así que
un cliente con otro acento tenía botones brillando de un color que no era el
suyo; ahora se deriva de `--nh-accent`.

Las tarjetas de cierre (`cta`) ganaron un degradado radial de acento al 20% de
alpha (empezó en 15%, se subió porque no se notaba), centrado a un tercio
desde la esquina superior derecha (empezó en el vértice exacto y la mitad de
la mancha caía fuera de la tarjeta), en su propia capa con `aria-hidden` y sin
eventos. Sobre fondos que **ya son** el color de acento, el brillo usa una
variante más clara — el normal ahí era acento sobre acento y no se veía. Las
utilidades de `color-mix` quedaron dentro de un `@supports`: sin eso, el
compilador emitía un respaldo que sustituía la mezcla por el acento a opacidad
completa — una mancha sólida tapando la tarjeta, más visible que el defecto
que se corregía.

## 6. Aliados (`partners`): logos y carrusel sin JavaScript

Los aliados pasaron de texto a logos en tarjetas blancas (`932d5f2`), con el
mismo borde opcional y la misma paleta cerrada que "Qué hacemos" — el
`Fieldset` del borde se compartió entre las dos secciones. Cinco visibles; a
partir del sexto, un carrusel en bucle.

**El bucle es CSS puro**, sin JavaScript: el sitio se sirve sin
`unsafe-inline`, y un carrusel con script habría sido la única pieza de la
página que obligara a relajar esa política por una decoración. La lista se
dibuja dos veces y la pista se desplaza el ancho de una copia; la segunda
copia va `aria-hidden` para que un lector de pantalla no lea cada aliado dos
veces. `prefers-reduced-motion` detiene la animación y deja la pista
desplazable a mano — detenerla sin más habría dejado el resto de los logos
inalcanzables.

El desplazamiento exacto **no** es `-50%`: con N tarjetas por copia, la pista
mide 2N tarjetas y 2N−1 huecos, así que la mitad geométrica del ancho se queda
corta por medio hueco. Medido con 8 aliados: pista de 3688px, `-50%` habría
desplazado 1844px, la vuelta real cierra en 1856px — un salto de 12px por
vuelta si no se corrige. Se compensa sumando medio `gap` al cálculo.

El logo es opcional (los aliados ya cargados sólo tenían nombre) y el `alt`
**no se pregunta**: para un logotipo de aliado, su texto alternativo es su
nombre, y preguntarlo aparte sería pedir dos veces lo mismo para que la
segunda respuesta quede peor — lo escribe el compilador.

## 7. Nuestra Historia, Valores y Equipo: antetítulos, fotos y rediseño

### 7.1 Antetítulo y foto en `rich_text`, antetítulo en `values` (`7e76d9f`)

Los tres campos nuevos son opcionales porque `rich_text` también arma la
entrada de contacto y `values` también arma "¿Qué incluye?" de Inversionistas
— exigirlos habría invalidado esas secciones de golpe. Con foto, "Nuestra
Historia" se parte en dos columnas; sin foto sigue centrada a 720px, como
antes. Dos detalles de implementación: los campos de imagen se declaran con
nombres **relativos** (nacieron para vivir dentro de un repeater que los
ancla) y, sueltos, el compilador no los encontraba — el contenedor los ancla
ahora explícitamente. Y la vista previa de la foto bajó de 1013×570px a
235×132px con un `Grid` propio — el `columns()` del fieldset no repartía el
ancho como se esperaba.

### 7.2 Rediseño del equipo (`68063c6`, `667f2bd`)

El render del CMS no respetaba el diseño publicado: fondo plano y nombres
sueltos, donde el sitio tiene una banda de color a todo el ancho con tarjetas
blancas y sombra. Se recuperó ese diseño, y el color de la banda y del
encabezado pasaron a ser **elegibles** desde el formulario.

Los retratos pasan a 9:16 (eran cuadrados y cortaban fotos de cuerpo entero
por la mitad); la vista previa del panel usa la misma proporción, así que lo
que el owner encuadra al subir es lo que se publica.

El bloque "Destacado" ganó antetítulo y **logo propio**, porque puede
representar una división con identidad comercial distinta (el ejemplo real
del owner: un despacho de arquitectura asociado). La decisión de fondo: pasó
de tres claves sueltas `spotlight_*` a un objeto `spotlight` anidado con
`media_id` adentro — el pipeline de imágenes entero (validación, promoción,
reporte de huérfanas) recorre el payload buscando esa clave exacta, así que
un `spotlight_media_id` plano habría sido invisible para los tres. La
migración de datos reescribe **tanto** los borradores como los snapshots ya
publicados; dejar los snapshots atrás habría hecho desaparecer el destacado de
páginas ya publicadas sin que nadie las tocara.

El destacado se adelgazó después (`667f2bd`): logo más grande (compite en
tamaño con su propio texto), menos aire interior y menos margen — con el
mismo padding en los cuatro lados la tarjeta pesaba junto a los retratos
altos y angostos. Medido: de ~200px de alto a 163px.

### 7.3 Rediseño de "Nuestros Valores" (`8b0012f`, `e8bbb56`)

El render del CMS dibujaba cada valor en una caja con borde y sombra; el sitio
publicado no tiene caja — una placa de ícono y el texto debajo, cuatro por
fila. Se recuperó ese diseño y cada valor ganó su propio ícono (misma
allowlist de `card_icons`), opcional para no invalidar los valores ya
publicados sin ícono.

Un detalle que casi se filtra: el ícono **no** se puede emparejar por índice
con el resultado de `rows()` (el helper que arma repeaters descartando filas
incompletas), porque ese helper **reindexa** al descartar — una fila a medio
llenar habría corrido todos los íconos siguientes un lugar. Se recorre el
`$state` directamente en vez de su forma procesada.

El fondo de toda la sección se volvió elegible (`e8bbb56`), con **"Fondo del
sitio" como default** y no blanco — el cuerpo de la página usa
`bg-site-background`, que es tematizable, y un default blanco fijo le habría
abierto un recuadro claro a cualquier cliente con otro color de fondo. Esa
decisión introdujo la entrada `site` en `brand_palette`.

## 8. Paleta de marca: de 10 a 18 colores, y su forma de elegirse

La paleta creció en tres pasos a lo largo del periodo:

1. **10 → 16**: se sumaron seis **neutros** (`neutral-0`…`neutral-5`, blanco a
   negro) con hexadecimal propio escrito — no se derivan del acento ni del
   principal del cliente, así que derivarlos habría dado negro. Van en la
   misma lista que los diez colores de marca para que exista **un solo**
   selector de color en todo el panel.
2. **+`site`**: el fondo configurado del sitio, como default y como "camino de
   vuelta" para las secciones que dejan elegir fondo (§7.3).
3. **+`navy`**: el azulado de los paneles suaves (`#eef1f8`), con hexadecimal
   propio — mezclar el principal con blanco da `primary-l2`, bastante más
   saturado, así que no es una variante derivable; es un tono fijo del sitio
   (§9).

**Total actual: 18 entradas** (`site` + 6 neutros + 5 variantes de acento + 5
de principal + `navy`). Cada entrada trae la clase para cada uso —
`bg`/`border`/`text`— en una sola estructura, porque dos listas paralelas se
separan la primera vez que alguien agrega un color en una sola.

### 8.1 De grilla fija a fichas por ancho mínimo (`667f2bd`)

Con la paleta en 16 colores, la grilla de 5 columnas fijas saltó a 4 filas y
se comía media pantalla en formularios con dos selectores. Se cambió a
`repeat(auto-fill, minmax(88px, 1fr))`: entran las que quepan por fila, sin
media queries, y sumar un color nunca vuelve a romper el alto del formulario.
El hexadecimal salió de la ficha visible al tooltip (ocupaba un renglón en
cada una de las dieciséis).

### 8.2 De paleta siempre desplegada a selector plegable (`c00640c`)

Con 18 colores y formularios con hasta tres selectores simultáneos ("Valores"
llegó a tener fondo de sección, placa del ícono y color del ícono), incluso
una grilla compacta era un muro de color donde no se distinguía cuál
selector era cuál. El selector pasó a un disparador chico —ícono (nuevo asset
`public/images/assets/color_picker_icon.png`), el color puesto y su nombre— que
abre un popover flotante de 8 columnas × 30px al hacer clic.

Dos defectos de Alpine.js encontrados montando el selector, invisibles
inspeccionando el HTML renderizado (aparecen sólo tras la hidratación del
cliente):

- **`x-bind:style` con una cadena reemplaza el atributo `style` entero.** Las
  fichas de color perdían tamaño y fondo, quedando en tiras de 30×6px con
  sólo el `box-shadow` del binding sobreviviendo. La forma correcta es un
  **objeto** (`x-bind:style="{ backgroundColor: hexActual }"`), que Alpine
  fusiona sobre lo que ya está escrito inline. El mismo defecto estaba **ya
  commiteado** en la galería de íconos en vivo (§9.2) y se corrigió a la vez.
- **`x-show` pisa `display` al mostrar el elemento.** Ponía `display:''`,
  borrando un `display:grid` escrito inline en el mismo nodo; el popover salía
  en `block` y las 18 fichas caían en una sola fila. La grilla tiene que ir en
  un **hijo** del elemento con `x-show`, nunca en el mismo nodo.

Los dos se encontraron montando un banco de pruebas estático con el markup
real y Alpine por CDN, y midiendo `getComputedStyle` / `getAttribute('style')`
en el navegador — no se veían leyendo el código ni el HTML servido por
Laravel.

Un guard de test preexistente (`FrontendCapabilityCardsTest`) exigía la grilla
por ancho mínimo de §8.1; se reescribió para verificar la **garantía** que
protegía (arranca plegado, el popover flota sin empujar el formulario, sin
elección se ve qué color hay puesto) en vez de la implementación puntual que
dejó de existir.

## 9. Color de ícono elegible: placa y glifo, con galería en vivo

### 9.1 `icon_bg_color` / `icon_color` en `values` y `capability_cards` (`6f04d7f`)

Petición explícita del owner: poder elegir el color de la envolvente
("placa") del ícono y el color del dibujo por separado. La regla, igual que
en `metrics` (§10.3): el color del **glifo** sólo se guarda si el owner lo
elige explícitamente; sin elección, sigue al color de su placa. No es
prolijidad — el color principal es tinta oscura, y elegir una placa oscura de
un solo clic (una acción razonable y esperable) dejaba el ícono invisible
entero si el glifo hubiera quedado fijo en principal. Sobre placa oscura se
usa el foreground que el contrato de contraste garantiza legible, no un
blanco fijo — el cliente puede tener un color principal claro.

Los dos selectores se ubicaron **pegados a la galería de referencia**, no en
un `Fieldset` separado como en el primer intento — feedback directo del
owner: "eso confunde". Quien está eligiendo el dibujo del ícono es quien está
decidiendo de qué color va.

### 9.2 Galería de íconos repintada en vivo (`be74ecd`)

Extensión pedida por el owner sobre §9.1: que la galería de referencia del
formulario cambie de color en el momento, sin publicar ni recargar. Se
resolvió **del lado del cliente** con Alpine, no con `->live()` de Filament
—elegir color es tantear varias opciones seguidas, y una vuelta de red al
servidor por cada intento se siente roto—. Alpine hace `$wire.$entangle()`
sobre la **misma** propiedad que escriben los dos selectores de §9.1.

La regla "sin elección, el glifo sigue a la placa" se duplica en JavaScript
por necesidad —el servidor no puede resolverla sin una vuelta de red—, pero lo
que se duplica es su **resultado**, no su criterio: la lista de qué placas son
oscuras se calcula en PHP con `BrandPalette::needsDarkText()` (el mismo método
que corre en el render público) y viaja al cliente ya resuelta. Un test
compara esa lista, extraída del HTML renderizado del editor, contra el
cálculo real de `needsDarkText()` sobre cada entrada de la paleta.

El bug de bind por cadena (§8.2) se compartía con este archivo y se corrigió
en el mismo commit que lo introdujo para el selector plegable.

## 10. La banda de cifras (`metrics`): divisores, color, aire

### 10.1 Divisores que cambian de eje según el layout (`98967e2`, `733f38a`, `b346a81`)

Las métricas ganaron una regla visual entre una y la siguiente — cuatro cifras
seguidas sin separador se leen como una lista, no como cuatro datos
distintos. La regla cambia de eje con el breakpoint: horizontal
(`h_divider.png`) mientras las métricas se apilan en una columna, vertical
(`v_divider.png`) cuando se acomodan en 2 o 4 columnas. Nunca se ven las dos a
la vez.

Cada regla se cuelga de la métrica que va **después** de ella, centrada en el
`gap-8` de la grilla. Por eso ninguna métrica que **abre fila** puede llevar
la vertical —quedaría flotando en el margen izquierdo de la tarjeta— y quién
abre fila cambia con el breakpoint porque la grilla pasa de 1 a 2 a 4
columnas: la regla exacta es `index % 4 === 0` → sin divisor,
`index % 2 === 1` → visible desde `sm`, resto → visible sólo desde `lg`. Con
las cuatro métricas por defecto el error no se ve; el schema permite hasta 12,
y a partir de la quinta se abre una segunda fila donde sí aparece.

Los PNG se ajustaron después (`733f38a`) para estirarse hasta los bordes de su
celda y fijar sólo el grosor (11px nativos), en vez de un tamaño fijo — así
escalan con el alto/ancho real de cada métrica. Los dos archivos estaban
**sin trackear en git** al principio; el deploy los habría servido 404 con la
banda llena de huecos. Hay un test `assertFileExists` sobre ambos.

### 10.2 Menos aire, color de tarjeta y de cifras (`86ca790`)

El padding vertical de la tarjeta bajó de 48px a 32px — pedido explícito del
owner ("los márgenes deben ser más chicos"). El fondo de la tarjeta y el color
de las cifras se volvieron elegibles con la misma paleta cerrada.

**El color de la cifra sólo se guarda si el owner lo eligió explícitamente**;
sin elección sigue al fondo de la tarjeta, con la misma justificación que
§9.1: el color principal es tinta oscura, y elegir una tarjeta oscura de un
solo clic hacía desaparecer el número entero si la cifra hubiera quedado fija
en principal. El primer intento de este cambio **no** tenía esa protección y
se corrigió después de reproducirlo en el navegador: tarjeta en principal,
cifras invisibles.

De paso se encontró y corrigió un defecto general de los cuatro selectores de
color existentes hasta ese momento (`cta`, `team`, `values`, `metrics`): la
ficha marcada por defecto no aparecía cuando la sección venía guardada de
**antes** de que ese selector existiera, porque `->default()` de Filament
sólo corre al **crear** un registro, no al editar uno existente sin la clave.
El panel mostraba el selector sin nada marcado mientras la página ya se veía
pintada con ese mismo color — contradicción visible entre panel y sitio. Se
corrigió leyendo `$field->getDefaultState()` en el propio blade del selector
y marcando la ficha correspondiente **sin escribir** el estado (escribirlo
habría ensuciado el formulario con sólo abrirlo).

## 11. Tipografía: familias configurables, negrita por sección, y un bug de carga

### 11.1 Petición del owner y su alcance exacto

Pedido original: negrita/normal elegible **por sección**, tanto en título como
en antetítulo, pero la **tipografía** (familia) configurada una sola vez de
forma global, no por sección — para no tener que decidirla en cada una de las
docenas de encabezados del sitio. La familia del título y la del antetítulo
son decisiones **independientes** entre sí (el ejemplo del owner: antetítulo
en negrita con una sans, título en una manuscrita).

### 11.2 Catálogo de tipografías (`2c61004`)

De 2 familias (Montserrat, Inter) a 8: se sumaron Playfair Display, Lora,
Space Grotesk y Caveat —compiladas por Vite vía `bunny()`, dos pesos cada
una— y Arial y Georgia, que son del **sistema** (`ThemeContract::SYSTEM_FONTS`)
y no se descargan. `ThemeContract::FONTS` es la lista cerrada única; agregar
una familia sin sumarla también a `vite.config.js` deja al owner eligiendo
algo que el navegador nunca encuentra y cae al fallback en silencio — hay un
test que compara las dos listas.

### 11.3 El antetítulo tenía tipografía fija por accidente

La utility `.eyebrow` apuntaba a `--font-display` — el token **fijo** del
sitio, no el del tema — así que elegir otra tipografía de títulos nunca había
tocado el antetítulo. Ganó su propio token (`--font-brand-eyebrow` /
`--nh-font-eyebrow`), configurable aparte, y el usuario recuperó el control
completo que había pedido: título y antetítulo con tipografía independiente.

### 11.4 Grosor por sección con tercer estado (`2c61004`)

El grosor (negrita/normal) tiene un **tercer estado** además de sí/no: "como
la configuración del sitio", que es el estado **ausente**. Un booleano de dos
estados habría obligado a copiar el valor global dentro de cada sección al
guardar, y desde ese momento cambiar la tipografía general habría dejado de
mover esa sección — exactamente lo que configurar el default en un solo lugar
buscaba evitar. `SectionTypography::title()`/`eyebrow()` resuelven la clase; el
selector de Filament (`TypographyFields`) usa `'1'`/`'0'`/`null` como opciones
con un `formatStateUsing()` que traduce desde/hacia el booleano guardado.

La clase de override lleva `!` (`font-bold!`): la utility heredada
(`font-weight-heading`, o `eyebrow` para el antetítulo) también fija
`font-weight`, y entre utilities de igual especificidad gana la que Tailwind
haya emitido último en el bundle — un orden que no se controla y que cambia
al agregar utilities nuevas. Sin el `!`, que una sección pueda pisar al
default del sitio dependería de un detalle de compilación.

12 tipos de sección declaran `title_bold`/`eyebrow_bold`; hay un test que
compara esa lista contra la constante `CON_ENCABEZADO` del formulario para que
agregar un tipo con encabezado y olvidar sumarlo a uno de los dos lados no
pase desapercibido.

### 11.5 El sitio no cargaba las tipografías que declaraba (`f6db521`)

**Bug preexistente**, no introducido por este trabajo — venía desde el
commit de fundaciones del frontend (`066e709`, anterior a toda esta épica) y
pasó desapercibido porque Montserrat se parece bastante a la tipografía de
reserva del sistema. Con una manuscrita (Caveat) quedó imposible de no ver.

Causa raíz: el plugin de fuentes de Vite (`laravel-vite-plugin/fonts`) compila
el CSS de las familias como un **chunk aparte** del manifest, sin marca
`isEntry` — ninguna entrada de `@vite(['app.css', 'app.js'])` lo importa. El
consumidor previsto, `Vite::fonts($aliases)`
(`Illuminate\Foundation\Vite::fonts()`), nunca se llamaba desde el layout. El
resultado: la variable CSS `--nh-font-heading` tenía el nombre correcto, el
`font-family` del elemento lo declaraba bien, y aun así el navegador dibujaba
todo con la tipografía de reserva porque **ningún `@font-face` había
llegado a la página**.

Se agregó `{{ Vite::fonts($fuentes) }}` al layout público
(`components/layouts/public.blade.php`), donde `$fuentes` sale de
`FrontendThemeService::fontAliases()` — sólo los alias de las **tres**
familias configuradas (título, cuerpo, antetítulo), no las 6 descargables del
catálogo completo: ofrecerle variedad al owner no puede costarle 6 descargas
a cada visitante del sitio. Las familias de sistema quedan fuera de esos
alias porque no tienen archivo en el manifiesto de fuentes, y pasarle a
`Vite::fonts()` un alias ausente lanza una excepción — si las tres familias
configuradas fueran del sistema, no se llama al helper en absoluto (sin
argumentos cargaría las 6).

Un guard existente (`FrontendPublicThemeCoverageTest`) prohibía la cadena
literal `font-display` en el HTML renderizado, como protección contra clases
Tailwind de rol fijo. Los `@font-face` que trae `Vite::fonts()` incluyen la
propiedad CSS `font-display: swap`, que **no es** la clase prohibida — el
guard se hizo preciso con un lookahead (`(?!\s*:)`) que distingue el uso como
clase del uso como propiedad, sin perder la protección original.

La verificación real de que una fuente carga **no** es
`document.fonts.check()` — devuelve `true` incluso sin ningún `@font-face`
cargado — ni `getComputedStyle().fontFamily` — devuelve lo declarado, no lo
que efectivamente se dibuja. La prueba que sirve es **medir**: el mismo texto
renderizado con `canvas.measureText()` da anchos distintos en la fuente real
contra la de reserva (320px en Caveat contra 422px en sans-serif, medido en
vivo).

### 11.6 La tipografía de títulos se usaba donde no correspondía (`1410833`)

Encontrado por el owner navegando el sitio con una tipografía de títulos
expresiva puesta: el menú principal, los botones, los filtros del catálogo,
"Ordenar por", el nombre y precio en las tarjetas de inmueble, los datos
destacados de `cta`/`metrics`, el nombre del asesor, las características del
inmueble en el detalle, y los nombres del equipo estaban **todos** dibujados
con la tipografía de títulos — un menú entero escrito en manuscrita.

Inventario y corrección por rol semántico, no por archivo: todo lo que es
**texto de interfaz** (navegación, botones, controles de filtro, precio,
nombre de propiedad) pasó a heredar la tipografía del **cuerpo**; todo lo que
funciona como **etiqueta** (encabezados de tarjeta en "Qué hacemos" y
"Nuestros Valores", "¿Qué incluye?" de Inversionistas, los valores de
características del inmueble como "3 recámaras") pasó a la tipografía del
**antetítulo**. Las etiquetas Venta/Renta/Residencial (el componente
`badge.blade.php` y sus usos en tarjetas de propiedad y proyecto) se sumaron
al mismo barrido en un pase posterior (`6f04d7f`).

Un guard de marca preexistente (`FrontendBrandUtilitiesTest`) exigía
literalmente la cadena `font-brand-heading` en `button.blade.php` y en el
layout público — y era, sin darse cuenta, **la causa** de que el menú y los
botones estuvieran mal tipografiados: el test forzaba justo el error que el
owner reportó. Se reescribió para comprobar lo que el contrato realmente pide
—que ningún archivo fije una familia tipográfica **constante**— con un helper
compartido (`assertNoFixedFontFamily`) en vez de exigir una familia concreta;
heredar la del cuerpo cumple esa regla, nombrar `font-brand-heading` a secas
no la garantizaba y de hecho la violaba en la práctica.

## 12. Vista previa del tema en la configuración del sitio (`3eec2e7`, `4dc51c4`)

Con nueve controles de tema repartidos en el formulario de configuración
(colores, tipografías, pesos, redondeo), la única forma de saber si el
conjunto funcionaba junto era guardar y salir a mirar el sitio público.
`FrontendSettingsPage` ganó una maqueta en vivo, a todo el ancho, con hero
+ banda de tarjetas usando los valores del formulario sin guardar.

**Decisión central: la vista previa pide el tema NORMALIZADO, no los valores
crudos del formulario.** `FrontendThemeService::build()` se dividió para
extraer `public function normalize(array $stored): array`, y la vista previa
llama a esa misma función — no repite sus reglas por su cuenta. Si un par de
colores no llega a AA y el owner no activó "permitir bajo contraste", el sitio
sustituye la tinta por una legible; si la vista previa dibujara el color tal
cual se escribió en el campo, mostraría un bloque casi ilegible mientras el
sitio publicado se ve perfectamente bien — justo el caso en que mirar la
vista previa importa más.

El panel de Filament compila su propio CSS (`build:filament`, Tailwind v3,
config aparte) y no incluye ninguna utility del sitio público: la vista
previa entera va en `style` inline, igual que los selectores de paleta. Y
como el panel sólo cargaba Inter y Montserrat, hubo que sumar las cuatro
familias nuevas al `@import` de `resources/css/filament/admin/theme.css` —
sin eso, la vista previa habría dibujado Caveat en Montserrat, mintiendo
sobre lo que el sitio realmente publica.

La muestra de redondeo de esquinas, que antes era una maqueta separada con un
gris fijo inventado, se plegó **dentro** de la vista previa general
(`4dc51c4`), arriba a la derecha del hero — pedido explícito del owner. Se
dibuja con el fondo real del sitio en vez del gris fijo, así se lee sobre
cualquier color principal, incluso uno claro.

## 13. Deuda y trabajo explícitamente fuera de este periodo

Registrado para que no se pierda ni se reintente por accidente:

- **`site/inversionistas.blade.php` no puede ganar colores de ícono sin
  migrar a una sección del CMS.** Es una página **estática**
  (`Route::view('/inversionistas', 'site.inversionistas')`); su registro en
  `frontend_pages` existe pero con **cero secciones**, así que no hay
  formulario donde agregar un selector. El owner pidió íconos elegibles ahí
  ("Alcance del servicio") y quedó fuera de alcance por esta razón concreta,
  no por descarte.
- **`featured_projects` sigue sin botón al catálogo de proyectos.** Es el
  mismo patrón que `featured_properties` (§3.1) y `opportunity_properties`
  (§3.2); una línea en `SectionPayloadCompiler::CATALOGO` lo resolvería,
  pero no se pidió explícitamente en este periodo.
- **El fallback opaco de `color-mix()`** para `--shadow-cta` y
  `--shadow-whatsapp` (distinto del ya envuelto en `@supports` de §5.4, que
  cubre sólo el brillo radial) sigue sin envolverse.
- **Archivos de foto mayores a 12MB** siguen bloqueando el formulario en
  silencio por validación nativa HTML5, el mismo mecanismo de §1.2 pero por
  encima del límite ya subido.
- **No existe comando de purga manual** para las imágenes públicas que quedan
  huérfanas al reemplazar media ya publicada. `frontend:media:report-unreferenced`
  las **lista**; el comando que las borra con confirmación no se construyó.

## 14. Archivos tocados — resumen por área

No es un inventario exhaustivo (está en cada commit); orienta dónde vive cada
pieza.

| Área | Archivos principales |
| --- | --- |
| Formulario de secciones | `app/Filament/Resources/FrontendPageResource/RelationManagers/SectionsRelationManager.php`, `HeroRelationManager.php` |
| Compilación de payload | `app/Filament/Forms/Sections/SectionPayloadCompiler.php` |
| Schema / contrato | `app/Services/Frontend/FrontendSectionSchema.php` |
| Paleta de marca | `app/Services/Frontend/BrandPalette.php`, `config/frontend-sections.php` (`brand_palette`, `card_icons`) |
| Tipografía | `app/Support/Frontend/ThemeContract.php`, `app/Support/Frontend/SectionTypography.php`, `app/Filament/Forms/Components/TypographyFields.php`, `app/Services/Frontend/FrontendThemeService.php`, `resources/css/app.css`, `vite.config.js` |
| Selectores de color e íconos (panel) | `resources/views/filament/forms/color-palette.blade.php`, `resources/views/filament/forms/icon-gallery.blade.php`, `public/images/assets/color_picker_icon.png` |
| Configuración del sitio y vista previa | `app/Filament/Pages/FrontendSettingsPage.php` |
| Vistas de sección (público) | `resources/views/frontend/sections/*.blade.php` (los doce tipos con encabezado) |
| Layout público | `resources/views/components/layouts/public.blade.php`, `resources/views/components/button.blade.php`, `resources/views/components/badge.blade.php` |
| Catálogo de propiedades | `app/Http/Controllers/PropertyController.php`, `resources/views/inmuebles/index.blade.php`, `show.blade.php` |
| Media de propiedades | `app/Models/Property.php` (`deferCoverGuard`), `app/Filament/Resources/PropertyResource/Pages/EditProperty.php` |
| Divisores de métricas | `public/images/assets/v_divider.png`, `h_divider.png` |

## 15. Verificación al cierre del periodo

Suite Frontend completa: **872/872**, código de salida 0, verificado en cada
uno de los 44 commits antes de avanzar al siguiente (nunca se acumuló trabajo
sin la suite en verde). Ningún cambio de este periodo se consideró cerrado sin
verificación en el navegador real —medidas de `getComputedStyle`, capturas de
pantalla, o comparación de HTML servido— además de la suite automatizada; la
sección 0 de este documento detalla por qué esa doble verificación fue la
regla, no la excepción.

No hubo auditoría externa de este periodo. Si en un incremento posterior se
decide someterlo a una, este documento es su punto de partida — cubre el qué
y el porqué de cada pieza; la matriz de tests exhaustiva vive en el código
(`tests/Feature/Frontend/`), no se duplica acá.
