# Módulo de Generación de CV / Perfil en PDF

Este módulo genera un documento PDF profesional con los datos de un egresado de la UT de la Costa. Incluye información personal, resumen, habilidades, experiencia laboral, resultados de pruebas psicométricas/técnicas y un gráfico de radar comparativo.

## Endpoint

**`GET /api/egresados/{id}/cv/pdf`**

### Parámetros opcionales (Query string):

- `vacante_id` (int): ID de una vacante. Si se proporciona, el PDF incluirá el perfil ideal de esa vacante y calculará el porcentaje de idoneidad, comparando las puntuaciones reales contra el perfil ideal en el gráfico de radar.
- `download` (int): Si se establece en `1` (`download=1`), los headers de respuesta forzarán la descarga del archivo (`attachment`). Si no, se intentará abrir el archivo dentro del navegador (`inline`).

### Ejemplos de uso con `curl`:

1. Ver el perfil base sin descargar:
   ```bash
   curl -I "http://localhost:8000/api/egresados/1/cv/pdf"
   ```

2. Descargar el perfil de un egresado:
   ```bash
   curl -o cv.pdf "http://localhost:8000/api/egresados/1/cv/pdf?download=1"
   ```

3. Descargar el perfil comparado con la vacante 3:
   ```bash
   curl -o cv-vacante.pdf "http://localhost:8000/api/egresados/1/cv/pdf?vacante_id=3&download=1"
   ```

## Dependencias

- **[Dompdf/Dompdf](https://github.com/dompdf/dompdf)**: Encargada de transformar HTML+CSS a PDF.
- **[QuickChart.io](https://quickchart.io/)**: API externa gratuita usada para renderizar el gráfico de radar (`RadarChartService.php`). Si QuickChart está inactivo, el PDF seguirá generándose de manera segura omitiendo la gráfica pero conservando los puntajes.

## Estructura de archivos

- `app/controllers/CvPdfController.php`: Valida las peticiones y llama al servicio.
- `app/services/CvPdfService.php`: Construye los datos y renderiza el PDF.
- `app/services/RadarChartService.php`: Llama a la API de QuickChart para generar un PNG en Base64.
- `app/models/CvEgresadoRepository.php`: Repositorio para extraer datos del egresado.
- `app/models/CvVacanteRepository.php`: Repositorio para extraer datos de la vacante.
- `app/views/pdf/cv_egresado.php`: Plantilla HTML con estilos incrustados compatibles con Dompdf.
- `public/assets/img/logo-ut-costa.png`: Logo institucional (reemplazar la imagen actual por la de la Universidad).
- `database/optional_cv_pdf_support.sql`: DDL opcional para crear las tablas de experiencia, habilidades y certificaciones si no existen en tu esquema base.

## Integración con Frontend (Angular)

Desde el frontend, se debe procesar la respuesta como `blob` si se usa el HttpClient de Angular:

```typescript
descargarCV(egresadoId: number, vacanteId?: number) {
  let url = `http://localhost:8000/api/egresados/${egresadoId}/cv/pdf?download=1`;
  if (vacanteId) {
    url += `&vacante_id=${vacanteId}`;
  }
  
  this.http.get(url, { responseType: 'blob' }).subscribe(blob => {
    const downloadUrl = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.download = `cv-egresado-${egresadoId}.pdf`;
    link.click();
    window.URL.revokeObjectURL(downloadUrl);
  });
}
```

## Posibles errores

- `400 Bad Request`: El ID del egresado no es numérico.
- `404 Not Found`: No se encontró al egresado en la base de datos.
- `500 Internal Server Error`: Falla la base de datos o algún problema de configuración interna del servidor (e.g. timeout excesivo).
