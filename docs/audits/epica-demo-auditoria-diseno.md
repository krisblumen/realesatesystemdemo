# Auditoría de diseño — Épica DEMO Demo público multi-inquilino

> **Limitación de esta auditoría, dicha por delante**: la escribió quien escribió
> el diseño. Sirve para los defectos que se encuentran releyendo con intención de
> romper, y ya encontró tres críticos. No sirve para los puntos ciegos
> compartidos: si el diseño parte de un supuesto equivocado, esta auditoría parte
> del mismo. **Falta una pasada con contexto fresco antes de implementar.**

Documentos auditados:
- `docs/rfc/EPICA-DEMO-MULTI-INQUILINO.md`
- `docs/epicas/epica-demo-multi-inquilino.md`

## Evidencia verificada en código real

- `Panel::domain()` y `Panel::domains()` existen —
  `vendor/filament/filament/src/Panel/Concerns/HasRoutes.php:50` y `:60`. La
  sección 8.1 no inventa una capacidad.
- **`CREATE DATABASE cannot run inside a transaction block`**. Verificado contra
  Postgres 18.3 con `BEGIN; CREATE DATABASE ...; COMMIT;`.
- `CREATE DATABASE ... TEMPLATE` falla con conexiones sobre la plantilla:
  `source database "demo_template" is being accessed by other users`.
  Documentado igual en Postgres 16 y 18.
- Claves de caché sin inquilino en cinco servicios de `app/Services/Frontend/`.
- Caché, cola y sesión sobre `database` (`php artisan about`).

## 1. Veredicto

**El diseño es implementable, pero no como está escrito.** La estrategia de
aislamiento —base por inquilino— es correcta y el análisis del problema es
sólido: identifica las tres superficies que la base de datos no cubre (caché,
archivos, sesión) y las cierra.

Los tres hallazgos críticos no tumban la estrategia; tumban mecanismos concretos.
Uno de ellos —el cerrojo— está directamente contradicho por el comportamiento
real de Postgres.

**No apto para pasar a implementación sin corregir C-1, C-2 y C-3.**

> **Estado tras la corrección**: los tres críticos están cerrados en el documento
> de épica (8.6, 8.6.1, 8.12) y aparecen marcados abajo. Siguen abiertos los
> medios y menores, y sigue faltando la pasada con contexto fresco.

## 2. Hallazgos críticos

### C-1 — El cerrojo propuesto no se suelta si el trabajo falla, y la variante que sí lo haría es imposible

> **CORREGIDO** en la sección 8.6 del documento de épica.

La sección 8.6 dice:

> Serializada con un cerrojo de aviso de Postgres (`pg_advisory_lock`) [...] Si
> el worker muere a mitad de la copia, el cerrojo de Postgres se suelta solo al
> cerrarse la sesión.

Eso es falso en el caso que importa. `pg_advisory_lock` es de **sesión**, y la
sesión de un worker de cola **no se cierra entre trabajos** — el worker es un
proceso largo que reusa la conexión. Si el trabajo lanza una excepción después de
tomar el cerrojo, el cerrojo queda tomado mientras el worker siga vivo. Todas las
altas siguientes se cuelgan esperando, y no hay error que lo delate: se ven como
lentitud.

La salida natural sería `pg_advisory_xact_lock`, que se suelta al terminar la
transacción. **No se puede**: `CREATE DATABASE cannot run inside a transaction
block` (verificado). El cerrojo transaccional exige una transacción, y la
operación que hay que proteger no puede estar dentro de una.

Corrección requerida:

1. `pg_advisory_unlock` en un `finally`, no en el camino feliz.
2. ~~Un barrido al arrancar el worker que suelte cerrojos de la épica
   huérfanos.~~ **Esta prescripción era incorrecta y no se aplicó.** Si el worker
   muere, su sesión de base de datos se cierra y Postgres suelta el cerrojo solo:
   no hay cerrojo huérfano que barrer. El único caso real es el del trabajo que
   falla con el worker vivo, y ese lo cubre el `finally` del punto 1. El barrido
   habría sido ceremonia sin causa. Queda anotado en 8.6 por qué no está.
3. Un tiempo máximo de espera al tomar el cerrojo (`pg_try_advisory_lock` en
   bucle con límite), para que una alta bloqueada falle con un mensaje en vez de
   colgarse para siempre.

Nota: el barrido **sí** hace falta para las bases de prueba (8.12), y por la
razón opuesta — una corrida de tests interrumpida deja bases reales que no
desaparecen solas. Que las dos cosas suenen parecidas y necesiten respuestas
contrarias es justamente por qué conviene escribirlo.

### C-2 — El nombre de la base sale de un dato del visitante y se interpola en DDL

> **CORREGIDO**: sección 8.6.1 nueva, dedicada al tema, más el contrato C-8.

El diseño deriva la base del inquilino de su `slug`, y el `slug` nace del
registro público. `CREATE DATABASE` es DDL: **Postgres no acepta parámetros
enlazados para identificadores**, así que el nombre se interpola en la sentencia
sí o sí.

El documento roza el tema —"`database`: nombre real de la base; no se deriva del
slug al vuelo"— pero nunca lo nombra como lo que es: si el `slug` no está
validado contra una lista blanca estricta antes de tocar la base, esto es
inyección SQL ejecutada por un rol con permiso para crear y borrar bases de
datos. En un formulario abierto a cualquiera.

Corrección requerida:

1. El `slug` se **genera del lado del servidor**, no lo elige el visitante. Si
   el visitante puede sugerirlo, se valida contra `^[a-z][a-z0-9]{7,31}$` y se
   rechaza todo lo demás.
2. El nombre de la base se compone de un prefijo fijo más el slug ya validado, y
   se vuelve a validar inmediatamente antes de la sentencia.
3. El rol que crea bases no es el rol con el que corre la aplicación en las
   peticiones.

Este es el hallazgo más grave del conjunto, porque el resto del diseño protege
inquilino contra inquilino y este permite saltar por encima de todos.

### C-3 — El test que justifica la épica no tiene forma de correr

> **CORREGIDO**: sección 8.12 nueva, contrato C-9, y movido al lote A.

La matriz de tests (sección 10) empieza con:

> Dos inquilinos publican home distinta; cada uno ve la suya, con caché caliente.

Y el documento dice, correctamente, que si sólo se pudiera escribir un test sería
ese. **Pero el diseño no dice cómo se monta.** La suite actual corre con
`RefreshDatabase` contra una sola base (`demo_test`). Un test de dos inquilinos
necesita dos bases reales, creadas y destruidas por el test, con conmutación de
conexión en el medio — y `RefreshDatabase` envuelve todo en una transacción, que
además es incompatible con `CREATE DATABASE` (ver C-1).

Sin resolver esto, los siete contratos quedan sin verificación real y la épica se
implementa a ciegas.

Corrección requerida: un caso base de test propio para inquilinos, que cree bases
efímeras desde la plantilla, no use `RefreshDatabase` para ellas, y las borre al
terminar. Es trabajo del **lote A**, no del final: sin él no se puede verificar
nada de lo que venga después.

## 3. Hallazgos medios

### M-1 — `pg_terminate_backend` no impide reconectar

La sección 8.8 corta las conexiones y después borra. Entre las dos operaciones
hay una ventana: el navegador del visitante reintenta, se reconecta, y el `DROP
DATABASE` falla. En un demo con pestañas abiertas eso no es raro, es lo normal.

Falta revocar `CONNECT` sobre la base antes de terminar las sesiones. El orden
correcto es revocar, terminar, borrar.

### M-2 — El plazo de vida y los límites de abuso no tienen números

El RFC habla de "credenciales temporales" y de "cuántos inquilinos por origen y
por unidad de tiempo", y el documento de épica no define ninguno de los dos. Sin
un número, el lote F no se puede implementar ni revisar. Y el plazo determina
cuántas bases coexisten, que es el límite operativo real del diseño.

### M-3 — La entrega de credenciales no está diseñada, y es un punto de falla

El flujo dice "el visitante recibe su acceso". Si es por correo y el correo
falla o cae en spam, el inquilino queda aprovisionado y ocupado, y el visitante
sin entrar. En un demo público eso es la mayoría del embudo.

Falta decidir: entrega en pantalla, por correo, o ambas; y qué pasa con el
inquilino si nadie entra nunca.

### M-4 — El padrón del operador es una frase, no un contrato

"El operador ve el padrón de inquilinos, no el contenido de ninguno" aparece en
el RFC y no se vuelve a mencionar. No hay definición de dónde vive esa pantalla,
con qué autenticación, ni cómo se garantiza técnicamente que no pueda abrir el
contenido de un inquilino.

### M-5 — Nada verifica la regla de oro 2

"La aplicación jamás abre una conexión contra la plantilla" es la regla de la que
depende que las altas funcionen, y es la única sin mecanismo que la haga cumplir.
Alcanza con que alguien agregue la plantilla a una configuración de diagnóstico
para romper el alta, y el síntoma aparecerá lejos de la causa.

Debería haber una verificación automática: la plantilla no figura en ninguna
conexión configurada.

## 4. Hallazgos menores

### Mn-1 — Sobra `->domain()` en el panel

La sección 8.1 propone montar el panel con `->domain()`. **No hace falta.** Las
rutas que no declaran dominio matchean cualquier host, y el middleware ya
resuelve el inquilino leyendo el `Host`. Sólo las rutas del registro necesitan
quedar acotadas al host central.

Declarar el dominio en el panel además introduce un parámetro de ruta `{tenant}`
en toda la generación de URL de Filament, que después hay que resolver con
`URL::defaults()`. Es trabajo y superficie de error a cambio de nada.

### Mn-2 — La sal de `origen_ip_hash` no tiene dueño

Si la sal rota, los límites por origen se pierden en silencio. Falta decir dónde
vive y que no rota.

### Mn-3 — `template_version` se guarda y nadie la usa

Se anota en la fila del inquilino "para diagnosticar", pero ningún proceso la
lee. O se define para qué sirve, o sobra.

## 5. Sobreingeniería detectada

**Poca, y una sola confirmada**: el `->domain()` del panel (Mn-1).

La versión de plantillas (8.7) **parece** sobreingeniería y no lo es: existe
justamente para evitar la carrera entre migrar la plantilla y dar de alta, que es
el momento exacto en que Postgres rechaza la copia. Se sostiene.

El prefijo de inquilino en las claves de caché (8.3) es redundante hoy con el
aislamiento por base. Se sostiene también, y el documento explica por qué: es
seguro contra un cambio de infraestructura probable.

## 6. Riesgos de implementación

- **El más silencioso sigue siendo la conexión heredada entre trabajos** (C-4 del
  diseño). El diseño lo identifica bien, pero no propone mecanismo: dice que cada
  trabajo "resuelve y restaura". Eso es disciplina, y la regla de oro 1 del
  propio documento dice que la disciplina no es protección. Debería ser un
  middleware de trabajo obligatorio, no una convención.
- El orden de middleware (C-1 del diseño) es frágil frente a cualquier paquete
  que se agregue después y se registre antes.

## 7. Riesgos de seguridad

| Riesgo | Gravedad | Estado en el diseño |
|---|---|---|
| Inyección en el nombre de la base | **Alta** | No contemplado (C-2) |
| Fuga por caché compartida | Alta | Contemplado y resuelto (8.3) |
| Colisión de archivos entre inquilinos | Alta | Contemplado y resuelto (8.4) |
| Sesión leída de la base equivocada | Alta | Contemplado y resuelto (8.2) |
| Escritura cruzada por conexión heredada | Alta | Identificado, sin mecanismo |
| Enumeración de inquilinos por subdominio | Baja | No contemplado; el slug generado por servidor lo mitiga |

## 8. Qué falta antes de implementar

1. ~~Corregir C-1, C-2 y C-3 en el documento de épica.~~ Hecho: 8.6, 8.6.1, 8.12.
2. ~~Confirmar el certificado comodín del hosting.~~ Hecho: Hostinger lo emite
   sólo en VPS. Y la sección 8.11 documenta que el VPS haría falta igual sin él.
3. Poner números a M-2, derivados del techo de bases del servidor.
4. **Una auditoría con contexto fresco.** Esta encontró lo que se puede encontrar
   releyendo; falta la que cuestione los supuestos.
