# SEO Audit Tool

Herramienta de auditoría SEO: introduces una URL, el backend descarga la página y analiza sus metaetiquetas (título, descripción, etc.), y el frontend muestra los resultados con gráficas.

## Stack

- **Backend** — [Laravel 13](https://laravel.com/) (PHP 8.3), API REST con jobs en cola para descargar y analizar páginas. Entorno de desarrollo con [Laravel Sail](https://laravel.com/docs/sail) (Docker): MySQL 8.4, Redis, Meilisearch y Mailpit.
- **Frontend** — [React 19](https://react.dev/) + [Vite](https://vite.dev/), con React Router, Axios para llamar a la API y Recharts para las gráficas.

## Estructura de carpetas

```
seo-audit-tool/
├── backend/    # API Laravel (modelos de auditoría, jobs, endpoints)
├── frontend/   # SPA React (formulario de auditoría y vista de resultados)
└── README.md
```

## Levantar el backend (Docker / Sail)

Requisitos: Docker Desktop en marcha y Composer.

```bash
cd backend
cp .env.example .env
composer install
./vendor/bin/sail up -d        # arranca los contenedores (app, MySQL, Redis...)
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan queue:work   # procesa los jobs de auditoría
```

La API queda disponible en `http://localhost`.

## Levantar el frontend

Requisitos: Node.js 20+.

```bash
cd frontend
npm install
npm run dev
```

Vite sirve la aplicación en `http://localhost:5173`.

## Scripts útiles del frontend

| Comando           | Qué hace                          |
| ----------------- | --------------------------------- |
| `npm run dev`     | Servidor de desarrollo con HMR    |
| `npm run build`   | Build de producción en `dist/`    |
| `npm run preview` | Sirve el build de producción      |
| `npm run lint`    | Linter (oxlint)                   |
