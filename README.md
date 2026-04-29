# backend-VitaEX

Backend en PHP con FlightPHP, Eloquent ORM y Rakit Validator.

## Estructura

```text
app/
  config/        Bootstrap, rutas, servicios y configuracion local
  controllers/   Mensajes HTTP, status codes y entrada/salida de endpoints
  exceptions/    Excepciones controladas para respuestas JSON
  middlewares/   Headers y reglas transversales de seguridad
  models/        Modelos Eloquent
  services/      Logica de negocio
  support/       Helpers de respuesta y errores API
  validators/    Reglas Rakit por recurso
  views/         Vistas simples de Flight
public/          Web root
```

## Instalacion

```bash
composer install
copy app\config\config_sample.php app\config\config.php
composer start
```

La API corre por defecto en `http://localhost:8000`.

## Configuracion

`app/config/config.php` esta ignorado por git. Puedes configurar variables de entorno:

```env
APP_ENV=local
APP_DEBUG=true
APP_TRACY=false
APP_TIMEZONE=America/Mexico_City
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vitaex
DB_USERNAME=root
DB_PASSWORD=
```

Si no hay base configurada, el backend arranca y responde `/api/health`, pero los endpoints que usan Eloquent devuelven `503` con mensaje controlado.

Para una prueba rapida con SQLite:

```powershell
php runway init:sample-db
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE='app/database.sqlite'
composer start
```

## Endpoints base

```text
GET    /api/health
GET    /api/users
GET    /api/users/{id}
POST   /api/users
PUT    /api/users/{id}
PATCH  /api/users/{id}
DELETE /api/users/{id}
```

Los controladores formatean la respuesta. La logica de negocio vive en services y la validacion vive en validators.
