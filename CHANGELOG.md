# Changelog

## [1.0.0] — 2026-09-21

Versión orientada a producción: seguridad, robustez, pruebas y documentación.

### Seguridad

- Protección completa frente a SSRF en todas las peticiones salientes: validación de esquema, puerto,
  credenciales y formato del host; bloqueo de nombres locales y de metadatos cloud; resolución DNS con
  bloqueo de IP privadas, reservadas, link-local, multicast y variantes IPv6; conexión fijada a la IP
  validada (`CURLOPT_RESOLVE`) contra DNS rebinding; redirecciones validadas una a una; límites de tiempo
  y de tamaño que cortan la transferencia.
- Rate limiting por IP, CORS restringido a orígenes configurados, cabeceras de seguridad y errores JSON
  sin información interna.
- Dependencias actualizadas: Guzzle, league/commonmark, react-router, nanoid y postcss (0 avisos en
  `composer audit` y `npm audit`).

### Backend

- Estados de auditoría con transiciones atómicas y cierre idempotente.
- Seis analizadores como servicios probados, con problemas por severidad, evidencia y recomendación.
- Nuevas comprobaciones: HTTPS, redirecciones, `lang`, viewport, `robots.txt`, sitemap, Open Graph,
  X-Robots-Tag, enlaces internos/externos, enlaces sin texto e imágenes sin `alt`.
- Puntuación documentada con pesos, redistribución y límite por problemas críticos.
- Jobs con reintentos para fallos transitorios, resultados sin duplicados y fallos parciales que no
  cancelan la auditoría; comando `audits:prune` programado.
- API con Form Requests, API Resources, paginación con filtros, eliminación y progreso por pasos.
- Migraciones incrementales que conservan los datos existentes.
- 301 tests (PHPUnit), Larastan nivel 8 y Pint.

### Frontend

- Nueva arquitectura por páginas, hooks y componentes; informe completo con métricas explicadas,
  recomendaciones filtrables y desglose de la puntuación.
- Polling sin solapamientos con reintentos y cancelación.
- Historial paginado con búsqueda y filtro en la URL; repetir y eliminar con confirmación.
- Accesibilidad (teclado, foco, landmarks, estados con icono y texto) y diseño responsive claro/oscuro.
- 76 tests (Vitest + Testing Library), oxlint con reglas de accesibilidad y Prettier.

### Proyecto

- README reescrito, capturas, licencia MIT, integración continua con GitHub Actions y Dependabot.
- Eliminados archivos predeterminados de Laravel y Vite que no se usaban.

## [0.1.0] — 2026-07-20

Primera versión: formulario de auditoría, análisis de metadatos, encabezados, palabras clave, enlaces
rotos y PageSpeed con jobs en cola, e historial básico.
