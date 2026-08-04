# RFC-13 Invitación

## Objetivo

Dar de alta un inquilino invitando a alguien, con un comando, sin registro
público ni infraestructura de cola.

## Épica

EPICA-DEMO. **Fase 1.** Depende de RFC-01, RFC-04 y RFC-06.

Reemplaza a RFC-11 mientras el demo sea por invitación.

## Responsable

Backend.

## Por qué existe

El demo arranca cerrado: se invita a quien uno quiere que lo pruebe. Eso borra de
un plumazo tres cosas que existían sólo porque cualquiera podía registrarse.

- **Los límites de abuso** (RFC-10). La invitación es el límite. Y desaparece la
  necesidad de guardar un hash del origen de la petición: menos código y un dato
  personal menos que retener.
- **La cola para el alta**, y sólo para el alta. Nadie está esperando en una
  pantalla: la dispara un humano desde la consola y puede tardar lo que tarde.
  **El worker y el cron siguen haciendo falta igual**, para la expiración y el
  borrado (RFC-09) y para los trabajos de fondo que el sistema ya tiene.
- **La serialización estricta** del alta. Dos invitaciones simultáneas no ocurren
  cuando quien invita es una persona escribiendo un comando.

## Alcance

Un comando:

```
php artisan demo:invitar {email}
```

Que hace, en orden:

1. Generar el `slug` con las reglas de RFC-05. **No se recorta nada de esa
   sección**, ver abajo.
2. Crear la fila del inquilino en la central (RFC-01).
3. Copiar la plantilla vigente (RFC-04).
4. Crear el usuario `owner` del inquilino con contraseña generada.
5. Marcar `activo` e **imprimir el acceso en la consola**: dirección, usuario y
   contraseña.
6. Imprimir el **aviso de contenido**: no subir al demo nada que no pueda ser
   público. La media publicada se sirve por el servidor web sin pasar por la
   sesión, y eso está aceptado como límite conocido (RFC-14). Quien invita tiene
   que trasladarlo a quien recibe la invitación.

Quien invita entrega esas credenciales por donde quiera. No hace falta correo, y
por lo tanto no hay un correo que pueda perderse — que era el punto de falla que
la auditoría marcó como M-3.

## Lo que NO se recorta, y por qué

### La validación del `slug` (RFC-05, sección 1)

Baja de gravedad pero no desaparece. Sigue siendo interpolación de un
identificador en DDL, y el rol que lo ejecuta puede borrar bases. Que hoy sólo lo
dispare un administrador no cambia lo que pasa si el valor está mal: cambia
quién puede provocarlo.

Además son cuatro reglas de validación. Recortarlas no ahorra trabajo real, y
volver a agregarlas cuando el demo se abra significa acordarse — y nadie se
acuerda.

### El cerrojo (RFC-05, sección 2)

**Se mantiene, en su versión mínima**: tomar con espera acotada, soltar en
`finally`. Son unas pocas líneas y evitan que dos comandos lanzados a la vez —o
un comando mientras se reconstruye la plantilla— den un error confuso de
Postgres en vez de un mensaje claro.

Lo que sí se va es la alerta de vigilancia sobre cerrojos tomados: con un humano
disparando altas, si algo se traba se ve al instante.

### Todo el aislamiento (RFC-02, 03, 06, 07, 08)

Intacto. **Con dos inquilinos ya se pisan**, y ninguna de esas piezas se puede
agregar después sin volver a recorrer cada clave de caché, cada ruta de medios y
cada trabajo en cola que existan para entonces.

RFC-03 en particular se mantiene aunque no haya cola para el alta: el sistema ya
tiene trabajos en segundo plano, y cualquiera de ellos que toque datos de un
inquilino tiene el mismo problema de conexión heredada.

## Qué pasa al abrir el demo

RFC-10 y RFC-11 dejan de estar en fase 2 y se implementan. Este RFC no se tira:
el comando de invitación sigue siendo útil para dar de alta demos internos y de
prueba sin pasar por el formulario.

## El plazo de vida en fase 1

`expira_en` lo fija el comando: un valor por defecto en configuración, que quien
invita puede cambiar por invitación.

El número deja de necesitar la aritmética de RFC-10 —que salía de cuántas bases
aguanta el servidor contra cuántos registros entran por día— porque con
invitación el numerador lo controla una persona. Pero el campo se sigue fijando
en el alta: un inquilino sin fecha de vencimiento es un inquilino eterno, y de
esos se juntan.

## Reglas

1. El comando no corre desde la web. Es de consola.
2. Imprime la contraseña una sola vez.
3. Rechaza invitar si ya hay un inquilino activo con ese correo.
4. **Respeta el tope duro de inquilinos activos** (RFC-10). Ese tope rige desde
   fase 1: no protege contra un ataque público, protege contra una operación
   humana masiva —invitar 80 correos con un bucle— que llenaría la instancia
   igual.

## Definition of Done

- El comando crea un inquilino usable de punta a punta.
- Un test verifica que un `slug` fuera de formato no llega a la sentencia.
- Un test verifica que dos invocaciones simultáneas no se corrompen entre sí.
- La contraseña impresa permite entrar al panel del inquilino.
