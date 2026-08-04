The signature New Hauz commerce unit — a property listing card. Price-led, photo zooms and the card lifts on hover, with a spec row for beds / baths / m² / parking.

```jsx
<PropertyCard
  image="/casa.jpg"
  title="Residencia en El Campanario"
  zone="El Campanario, Querétaro"
  price="$12,400,000" operation="Venta"
  beds={4} baths={4} area={420} parking={3}
/>
```

Falls back to a branded navy "FOTOGRAFÍA" placeholder when no `image` is given. Operation badge (Venta/Renta/Preventa) + optional `status` badge sit over the image. Use in a responsive 3/2/1-col grid.
