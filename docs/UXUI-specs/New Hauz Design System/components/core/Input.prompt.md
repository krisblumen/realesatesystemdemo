Lead-form field for New Hauz — 56px tall, Inter text, Montserrat label, orange focus ring. Supports real-time validation states.

```jsx
<Input label="Nombre" placeholder="Tu nombre completo" />
<Input label="Zona" as="select"><option>Juriquilla</option></Input>
<Input label="Mensaje" as="textarea" error="Campo requerido" />
```

Props: `label`, `hint`, `error` (red state), `iconLeft`, `as` (`input`|`textarea`|`select`). All native input attributes pass through.
