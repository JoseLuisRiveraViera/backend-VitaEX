# Integracion DENUE / INEGI

Configura el token en `.env` cuando lo tengas:

```env
DENUE_API_TOKEN=tu_token_inegi
DENUE_DEFAULT_LATITUDE=21.50
DENUE_DEFAULT_LONGITUDE=-104.89
DENUE_DEFAULT_RADIUS=5000
DENUE_DEFAULT_SEARCH_MODE=scian
DENUE_FILTER_SMALL_COMPANIES=true
```

Endpoints disponibles:

```http
GET /api/denue/carreras
GET /api/denue/empresas?carrera=sistemas
GET /api/denue/empresas?condicion=hospital&latitud=21.50&longitud=-104.89&radio=5000
POST /api/denue/empresas/importar
```

`/api/denue/empresas` acepta `carrera`, `condicion`, `palabra_clave` o `scian`.
Para carreras usa el mapeo SCIAN del documento DENUE y permite `tipo_busqueda=scian`
o `tipo_busqueda=keyword`.

`POST /api/denue/empresas/importar` puede recibir un arreglo `empresas` con los
resultados de DENUE o puede recibir los mismos parametros de busqueda para consultar
e importar en un solo paso. La importacion usa las tablas `empresa` y `ubicacion`
del DDL.
