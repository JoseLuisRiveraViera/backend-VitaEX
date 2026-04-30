# Bolsa de Trabajo UT de la Costa API

Backend PHP 8+, FlightPHP, Composer, PDO y PostgreSQL 16 para vinculación laboral entre egresados UT de la Costa y empresas.

## Requisitos

- PHP 8.1+
- Composer
- PostgreSQL 16
- Extensiones PHP: `pdo`, `pdo_pgsql`, `json`, `curl`

## Instalación

```bash
composer install
cp .env.example .env
php -S localhost:8000 -t public
```

Health check:

```http
GET http://localhost:8000/api/health
```

Respuesta esperada:

```json
{
  "success": true,
  "message": "API Bolsa de Trabajo UT funcionando",
  "data": {
    "status": "ok"
  }
}
```

## Configuración

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
DB_HOST=localhost
DB_PORT=5432
DB_NAME=bolsa_trabajo
DB_USER=postgres
DB_PASSWORD=password
DB_SCHEMA=bolsa_trabajo
FRONTEND_URL=http://localhost:4200
JWT_SECRET=change_me
JWT_TTL=3600
SIEST_AUTH_DRIVER=database
EXTERNAL_JOBS_API_URL=
```

La conexión PDO configura `SET search_path TO bolsa_trabajo`.

## Seed

El seed no modifica el DDL:

```bash
psql -h localhost -U postgres -d bolsa_trabajo -f database/seed.sql
```

## Roles

- `admin`: roles SIEst `1` o `22`
- `egresado`: rol SIEst `40`
- `empresa`: rol SIEst `41`

Con el DDL `siest_simulado_v3`, el backend autentica contra `vw_usuario_siest_login`/`usuario_siest` usando `password_verify()`. Ese es el modo por defecto con `SIEST_AUTH_DRIVER=database`.

Usuarios seed del DDL v3:

```json
{ "usuario": "hackaton-2026", "contrasena": "testing2026" }
{ "usuario": "egresado-2026", "contrasena": "testing2026" }
{ "usuario": "empresa-2026", "contrasena": "testing2026" }
{ "usuario": "admin-2026", "contrasena": "testing2026" }
```

Los endpoints reales de SIEst ya no son necesarios para el hackathon. Solo se usarían si cambias:

```env
SIEST_AUTH_DRIVER=remote
SIEST_LOGIN_URL=https://www.utdelacosta.edu.mx/SIEstBackend/api/v1/login
SIEST_EGRESADO_URL=https://www.utdelacosta.edu.mx/SIEstBackend/api/v1/egresados
```

El modo de autenticacion local con usuarios hardcodeados esta deshabilitado; usa `SIEST_AUTH_DRIVER=database` o `SIEST_AUTH_DRIVER=remote`.

## Endpoints

### Auth

- `POST /api/auth/login`
- `GET /api/auth/me`

### Egresados

- `GET /api/egresados`
- `GET /api/egresados/{cve_egresado}`
- `GET /api/egresados/{cve_egresado}/perfil`
- `PUT /api/egresados/{cve_egresado}/perfil`
- `GET /api/egresados/{cve_egresado}/postulaciones`
- `GET /api/egresados/{cve_egresado}/evaluaciones`
- `GET /api/egresados/{cve_egresado}/matching`
- `GET /api/egresados/{cve_egresado}/certificados`

### Empresas y Convenios

- `GET /api/empresas`
- `GET /api/empresas/{cve_empresa}`
- `POST /api/empresas`
- `PUT /api/empresas/{cve_empresa}`
- `GET /api/empresas/{cve_empresa}/vacantes`
- `GET /api/empresas/{cve_empresa}/candidatos`
- `POST /api/solicitudes-convenio`
- `GET /api/solicitudes-convenio`
- `PUT /api/solicitudes-convenio/{cve_solicitud_convenio}`

### Vacantes, Evaluaciones y Preguntas

- `GET /api/vacantes`
- `GET /api/vacantes/{cve_vacante}`
- `POST /api/vacantes`
- `PUT /api/vacantes/{cve_vacante}`
- `DELETE /api/vacantes/{cve_vacante}`
- `GET /api/vacantes/{cve_vacante}/candidatos?porcentaje_minimo=80`
- `POST /api/vacantes/{cve_vacante}/perfil-idoneo`
- `PUT /api/vacantes/{cve_vacante}/perfil-idoneo`
- `GET /api/tipos-prueba`
- `GET /api/evaluaciones/preguntas/{cve_tipo_prueba}`
- `POST /api/evaluaciones/iniciar`
- `POST /api/evaluaciones/{cve_evaluacion}/responder`
- `POST /api/evaluaciones/{cve_evaluacion}/finalizar`
- `GET /api/preguntas`
- `POST /api/preguntas`

### Postulaciones, Mensajes y Contrataciones

- `POST /api/postulaciones`
- `GET /api/postulaciones`
- `GET /api/postulaciones/{cve_postulacion}`
- `PUT /api/postulaciones/{cve_postulacion}/estatus`
- `GET /api/vacantes/{cve_vacante}/postulaciones`
- `GET /api/mensajes/egresado/{cve_egresado}`
- `GET /api/mensajes/empresa/{cve_empresa}`
- `GET /api/mensajes/vacante/{cve_vacante}`
- `POST /api/mensajes`
- `PUT /api/mensajes/{cve_mensaje}/leido`
- `GET /api/contrataciones`
- `POST /api/contrataciones`
- `PUT /api/contrataciones/{cve_contratacion}/confirmar-egresado`

### Certificados, Vacantes Nacionales y Reportes

- `POST /api/egresados/{cve_egresado}/certificados`
- `PUT /api/certificados/{cve_certificado}`
- `DELETE /api/certificados/{cve_certificado}`
- `PUT /api/certificados/{cve_certificado}/validar`
- `GET /api/vacantes-nacionales`
- `POST /api/vacantes-nacionales/sincronizar`
- `GET /api/reportes/insercion/pdf`
- `GET /api/reportes/convenios/pdf`
- `GET /api/reportes/egresado/{cve_egresado}/pdf`
- `GET /api/reportes/vacantes/excel`

### DENUE + TheirStack

- `GET /api/denue/empresas?carrera=TI&lat=21.8120&lon=-105.2080&radio=5000`
- `GET /api/theirstack/vacantes?empresa=Nayarit%20Software`
- `GET /api/theirstack/vacantes?empresa=Nayarit%20Software&titulos=developer,programador,javascript,python`
- `GET /api/mercado-laboral/empresas-vacantes?carrera=TI&lat=21.8120&lon=-105.2080&radio=5000&limit=10`

Flujo combinado:

1. DENUE busca empresas mexicanas cercanas por carrera usando palabra clave/SCIAN.
2. TheirStack verifica si esas empresas tienen vacantes activas.
3. TheirStack siempre se consulta con `job_country_code_or = ["MX"]` para limitar resultados a México.
4. TheirStack filtra por `job_title_or`; no se usa búsqueda por descripción.

Variables necesarias:

```env
DENUE_TOKEN=tu_token_inegi
DENUE_BASE_URL=https://www.inegi.org.mx/app/api/denue/v1/consulta/Buscar
THEIRSTACK_API_KEY=tu_api_key
THEIRSTACK_BASE_URL=https://api.theirstack.com/v1/jobs/search
THEIRSTACK_COUNTRY_CODE=MX
```

Para TI/Sistemas se usan títulos como `developer`, `programador`, `desarrollador`, `javascript`, `python`, `php`, `frontend`, `backend`, `full stack`. Puedes sobrescribirlos por request con `titulos=...`.

## Ejemplos JSON

Crear empresa:

```json
{
  "razon_social": "Tecnologías Costa S.A. de C.V.",
  "nombre_comercial": "Tecnologías Costa",
  "rfc": "TCO240101AB1",
  "sector": "Tecnologías de la información",
  "sitio_web": "https://tecnologiascosta.mx",
  "url_foto": "https://example.com/logos/tecnologias-costa.png",
  "correo_general": "contacto@tecnologiascosta.mx",
  "telefono_general": "3231000000",
  "zona": "norte_nayarit"
}
```

Crear vacante con perfil idóneo:

```json
{
  "cve_empresa": 1,
  "titulo": "Desarrollador Web Junior",
  "descripcion": "Desarrollo de aplicaciones con Angular, PHP y PostgreSQL",
  "area": "Tecnologías de la información",
  "modalidad": "hibrido",
  "salario_minimo": 10000,
  "salario_maximo": 15000,
  "estado": "publicada",
  "perfil_idoneo": {
    "puntaje_psicometrica": 75,
    "puntaje_cognitiva": 80,
    "puntaje_tecnica": 85,
    "puntaje_proyectiva": 70
  }
}
```

Actualizar perfil de egresado:

```json
{
  "telefono": "3231000001",
  "correo_personal": "egresado@gmail.com",
  "url_foto": "https://example.com/fotos/egresado-1.jpg",
  "url_cv": "https://example.com/cv.pdf",
  "resumen_profesional": "Backend PHP con PostgreSQL."
}
```

Evaluación:

```json
{ "cve_egresado": 1, "cve_prueba": 1 }
```

```json
{ "cve_pregunta": 1, "cve_opcion_respuesta": 1 }
```

```json
{ "estado": "finalizada" }
```

Postular a vacante:

```json
{ "cve_egresado": 1, "cve_vacante": 1 }
```

Cambiar a contratado:

```json
{ "estado": "contratado", "puesto": "Desarrollador Web Junior" }
```

Enviar mensaje:

```json
{
  "cve_postulacion": 1,
  "tipo_emisor": "empresa",
  "mensaje": "Nos interesa iniciar entrevista contigo."
}
```

Consultar empresas cercanas con vacantes activas en México:

```http
GET /api/mercado-laboral/empresas-vacantes?carrera=TI&lat=21.8120&lon=-105.2080&radio=5000&limit=10
```

Crear solicitud de convenio:

```json
{
  "cve_empresa": 1,
  "motivo": "Convenio para estadías e inserción laboral",
  "origen": "plataforma",
  "estado": "pendiente"
}
```

## Notas de implementación

- No se guardan contraseñas.
- Las consultas usan PDO y prepared statements.
- El backend no recalcula matching al postular; PostgreSQL lo hace con triggers.
- `DELETE /api/vacantes/{id}` usa soft delete con `estado = 'cancelada'` porque el DDL actual no tiene columna `activo`.
- Certificados usan `documento_egresado`; el delete es físico porque esa tabla no tiene columna `activo`.
- Preguntas usan `estado = 'inactivo'` como soft delete.
- Reportes usan `dompdf/dompdf` y `phpoffice/phpspreadsheet`.
