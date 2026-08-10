# RFC-078 Alta de demo por API

## Objetivo

Permitir que un sitio propio —hoy la landing de `www.landracore.com`— solicite el alta de un demo sin que el visitante tenga que volver a escribir su correo en `/guest`.

El visitante llena un formulario en la landing, y el demo se aprovisiona a partir de ese mismo dato. No ve un segundo formulario, no recaptura nada y no cambia de sitio.

## Épica

Demo autoservicio (fase 2)

## Responsable

Por asignar

## Estado

🟢 Implementado. Suite completa en verde (1788 tests).

---

## Contexto

`POST /guest` (`RegistroDeDemoController@store`) ya hace exactamente lo que hay que hacer: normaliza el correo, rechaza duplicados, comprueba los topes de RFC-10 y encola `AprovisionaUnInquilino`.

La tentación era llamar a esa misma ruta desde el servidor de la landing. No sirve, por tres motivos:

1. **Devuelve un redirect.** La respuesta es `back()` con una variable de sesión. Un llamador de servidor tendría que interpretar un 302 y leer sesión para saber si el alta procedió.

2. **Vive en el grupo `web`, con CSRF.** Habría que obtener un token antes de cada alta, o abrirle una excepción a CSRF — que es peor que el problema que resuelve.

3. **El tope por origen contaría al servidor, no al visitante.** `LimiteDeAltas::verificar()` recibe `$request->ip()`. Llamada desde la landing, *todas* las altas llegarían con la dirección del servidor, y el tope de tres por día cortaría el embudo completo al tercer visitante.

El tercero es el que decide. Y no falla de forma ruidosa: falla en silencio, un jueves a la tarde, con los leads guardándose correctamente y ningún demo creándose.

## Alcance

### Incluye

- `POST /api/demos`, ruta interna autenticada con secreto compartido.
- `App\Tenancy\SolicitaUnAlta`: las tres reglas previas al encolado, extraídas para que los dos caminos las compartan.
- `App\Tenancy\YaHayUnDemo`: el duplicado como excepción, para que cada camino lo traduzca a su forma de respuesta.
- `App\Http\Middleware\ExigeElSecretoDeAltas`.
- Grupo de rutas `api` con `ResolveTenant`.
- `tenancy.api.secreto` (`TENANCY_SECRETO_DE_ALTAS`).

### No incluye

- Versionado de la API. Los dos lados son nuestros y se despliegan juntos; versionar tiene sentido frente a consumidores que no controlás.
- Credenciales por cliente. Hoy hay un consumidor. Cuando haya más de uno y auditar quién pidió qué importe, se cambia el middleware — que es el único punto a tocar.
- Consulta de estado del alta. El acceso llega por correo; no hay nada que sondear.
- Entrega del acceso por respuesta HTTP. Sigue siendo por correo, y por el mismo motivo de siempre: cuando la petición responde, la base todavía se está copiando.

## Contrato

```
POST /api/demos
Authorization: Bearer <TENANCY_SECRETO_DE_ALTAS>
Content-Type: application/json

{ "email": "persona@ejemplo.com", "origen": "203.0.113.9" }
```

`origen` es la dirección **del visitante**, no la del llamador, y es obligatoria.

| Estado | Cuerpo | Cuándo |
| --- | --- | --- |
| `202` | `{"estado":"encolado"}` | El alta quedó en la cola `altas`. El inquilino todavía no existe. |
| `409` | `{"estado":"ya_existe"}` | Ese correo ya tiene un demo activo. |
| `429` | `{"estado":"sin_lugar","reintentar_desde":…}` | Tope por origen o tope de la instancia. `reintentar_desde` es nulo cuando el tope es de la instancia: no hay fecha que prometer. |
| `422` | Errores de validación | Correo u origen ausentes o malformados. |
| `401` | — | Secreto ausente o incorrecto. |
| `404` | — | Sin secreto configurado, o pedido a un host que no es el central. |

**202 y no 200** porque el alta se aceptó para hacerse, no se hizo.

## Seguridad

**Sin secreto configurado, 404 y no 401.** Un 401 confirma que hay una puerta; un 404 no dice nada. Y falla en la dirección correcta: desplegar sin configurar el secreto deja la puerta cerrada, no abierta.

**La comparación es `hash_equals`.** Con `===` el tiempo de respuesta filtra cuántos bytes acertó quien prueba.

**El origen es declarado, y vale lo que valga el secreto.** Si el secreto se filtra, el tope por origen se vuelve declarativo. Por eso el tope duro de la instancia se comprueba igual y no admite que nadie lo declare: es el que protege las 100 conexiones compartidas con la producción vecina, y no depende de la buena fe del llamador.

**El origen es obligatorio.** Si fuera opcional, omitirlo apagaría el tope por origen sin que nada fallara — exactamente el fallo silencioso que este RFC viene a evitar.

**`throttle:30,1` aunque haya secreto.** Protege el proceso web de que se martille la ruta probando llaves, y de que un bucle nuestro inunde la cola. Los topes de RFC-10 cuidan Postgres; éste cuida PHP. Ninguno reemplaza al otro.

**Sólo el host central.** Dar de alta inquilinos desde el subdominio de un inquilino no significa nada. Por eso el grupo `api` lleva `ResolveTenant`: sin él, `esElHostCentral()` decidiría sin que nadie hubiera resuelto el host.

## Definición de terminado

- [x] `POST /api/demos` encola el alta y responde 202.
- [x] El origen declarado es el que alimenta el tope, no la dirección del llamador.
- [x] El origen es obligatorio y se valida como dirección IP.
- [x] Duplicado responde 409; topes responden 429 sin encolar nada.
- [x] Sin secreto configurado la ruta no existe; con secreto incorrecto responde 401.
- [x] Sólo el host central atiende.
- [x] `RegistroDeDemoController` conserva su comportamiento, verificado por los 11 tests previos de `RegistroPublicoTest`.
- [x] `AltaPorApiTest` cubre los 12 casos; verificado por mutación que los tests del origen fallan si se reintroduce el uso de la IP del llamador.
- [ ] `TENANCY_SECRETO_DE_ALTAS` agregada a `.env.example` y configurada en el servidor.
