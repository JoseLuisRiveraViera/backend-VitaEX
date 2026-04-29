# Bolsa de Trabajo UT de la Costa API

Backend PHP 8+ con FlightPHP, Composer, PDO y PostgreSQL 16 para la plataforma Bolsa de Trabajo UT de la Costa.

## Estructura principal

```text
public/
  index.php
app/
  config/      env, cors y conexion PDO PostgreSQL
  core/        Response, Request, Validator y AuthMiddleware
  controllers/ Controladores REST
  models/      Consultas PDO preparadas
  routes/      Rutas API FlightPHP
  services/    SIEst, JWT y consultas de matching
```

## Ejecutar localmente

```bash
composer install
cp .env.example .env
```

Configura tus credenciales PostgreSQL en `.env`:

```env
DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=bolsa_trabajo_ut
DB_USER=postgres
DB_PASSWORD=postgres
DB_SCHEMA=bolsa_trabajo
```

Inicia el servidor:

```bash
php -S localhost:8000 -t public
```

Health check:

```http
GET http://localhost:8000/api/health
```

## Autenticación

El login real con SIEst está aislado en `app/services/SiestAuthService.php`. Por defecto usa simulación:

```env
SIEST_AUTH_MOCK=true
```

Para conectar al SIEst real:

```env
SIEST_AUTH_MOCK=false
SIEST_LOGIN_URL=https://www.utdelacosta.edu.mx/SIEstBackend/api/v1/login
```

No se guardan contraseñas en esta base de datos.

## Respuesta estándar

Éxito:

```json
{
  "success": true,
  "message": "Mensaje",
  "data": {}
}
```

Error:

```json
{
  "success": false,
  "message": "Mensaje de error",
  "errors": {}
}
```

## Ejemplos Postman

Crear empresa:

```json
{
  "nombre": "Tecnologías Costa",
  "rfc": "TCO240101AB1",
  "correo": "contacto@tecnologiascosta.mx",
  "telefono": "3231000000",
  "direccion": "Santiago Ixcuintla, Nayarit"
}
```

Crear vacante con perfil idóneo:

```json
{
  "cve_empresa": 1,
  "titulo": "Desarrollador Backend PHP",
  "descripcion": "Desarrollo de APIs REST con PHP y PostgreSQL.",
  "activo": true,
  "perfil_idoneo": {
    "puntaje_psicometrica": 80,
    "puntaje_cognitiva": 85,
    "puntaje_tecnica": 90,
    "puntaje_proyectiva": 75
  }
}
```

Actualizar perfil de egresado:

```json
{
  "telefono": "3231000001",
  "correo": "egresado@utdelacosta.edu.mx",
  "habilidades": "PHP, PostgreSQL, Angular",
  "experiencia": "Prácticas profesionales en desarrollo web"
}
```

Postular egresado a vacante:

```json
{
  "cve_egresado": 1,
  "cve_vacante": 1
}
```

Crear solicitud de convenio:

```json
{
  "cve_empresa": 1,
  "motivo": "Convenio para estadías e inserción laboral",
  "estatus": "pendiente"
}
```

Los payloads de empresa, vacante, perfil y solicitud se filtran contra columnas reales de PostgreSQL para no inventar campos; ajusta los nombres a los definidos en tu schema `bolsa_trabajo`.
