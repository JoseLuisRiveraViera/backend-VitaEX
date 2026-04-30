SET search_path TO bolsa_trabajo;

INSERT INTO ubicacion (pais, estado, municipio, localidad, codigo_postal) VALUES
('México', 'Nayarit', 'Santiago Ixcuintla', 'Centro', '63300'),
('México', 'Nayarit', 'Tepic', 'Centro', '63000'),
('México', 'Jalisco', 'Guadalajara', 'Centro', '44100')
ON CONFLICT (pais, estado, municipio, localidad, codigo_postal) DO NOTHING;

INSERT INTO carrera (nombre, clave_oficial, nivel) VALUES
('Tecnologías de la Información', 'TI', 'TSU'),
('Administración', 'ADM', 'TSU'),
('Procesos Alimentarios', 'PAL', 'TSU')
ON CONFLICT (nombre) DO NOTHING;

INSERT INTO tipo_prueba (categoria, nombre, descripcion) VALUES
('psicometrica', 'Psicométrica', 'Evaluación psicométrica general'),
('cognitiva', 'Cognitiva', 'Evaluación de razonamiento'),
('tecnica', 'Técnica', 'Evaluación técnica por perfil'),
('proyectiva', 'Proyectiva', 'Evaluación proyectiva')
ON CONFLICT (categoria) DO UPDATE SET nombre = EXCLUDED.nombre;

INSERT INTO prueba (cve_tipo_prueba, nombre, descripcion, es_banco_general)
SELECT cve_tipo_prueba, 'Banco general ' || categoria, 'Banco de preguntas base', true
FROM tipo_prueba
ON CONFLICT DO NOTHING;

INSERT INTO egresado (
    cve_persona_externa, cve_alumno_externa, matricula, nombre, primer_apellido,
    segundo_apellido, correo_institucional, correo_personal, telefono, url_foto,
    cve_carrera, cve_ubicacion, anio_egreso, resumen_profesional, url_cv
)
SELECT
    (12667 + n)::text,
    ('ALU' || lpad(n::text, 4, '0')),
    ('UTC' || lpad(n::text, 5, '0')),
    'Egresado ' || n,
    'Costa',
    'Demo',
    'egresado' || n || '@utdelacosta.edu.mx',
    'egresado' || n || '@example.com',
    '32310000' || lpad(n::text, 2, '0'),
    'https://example.com/fotos/egresado-' || n || '.jpg',
    (SELECT cve_carrera FROM carrera ORDER BY cve_carrera LIMIT 1),
    (SELECT cve_ubicacion FROM ubicacion ORDER BY cve_ubicacion LIMIT 1),
    2024,
    'Perfil profesional de prueba',
    'https://example.com/cv/egresado-' || n || '.pdf'
FROM generate_series(1, 10) AS n
ON CONFLICT (matricula) DO NOTHING;

INSERT INTO empresa (razon_social, nombre_comercial, rfc, sector, sitio_web, url_foto, correo_general, telefono_general, cve_ubicacion, zona)
VALUES
('Nayarit Software 1 S.A. de C.V.', 'Nayarit Software 1', 'NSO240101AA1', 'Tecnología', 'https://example.com/ns1', 'https://example.com/logos/ns1.png', 'contacto1@example.com', '3231000101', (SELECT cve_ubicacion FROM ubicacion WHERE municipio = 'Santiago Ixcuintla' LIMIT 1), 'norte_nayarit'),
('Nayarit Software 2 S.A. de C.V.', 'Nayarit Software 2', 'NSO240101AA2', 'Tecnología', 'https://example.com/ns2', 'https://example.com/logos/ns2.png', 'contacto2@example.com', '3231000102', (SELECT cve_ubicacion FROM ubicacion WHERE municipio = 'Santiago Ixcuintla' LIMIT 1), 'norte_nayarit'),
('Nayarit Software 3 S.A. de C.V.', 'Nayarit Software 3', 'NSO240101AA3', 'Servicios', 'https://example.com/ns3', 'https://example.com/logos/ns3.png', 'contacto3@example.com', '3231000103', (SELECT cve_ubicacion FROM ubicacion WHERE municipio = 'Tepic' LIMIT 1), 'norte_nayarit'),
('Nacional Data 1 S.A. de C.V.', 'Nacional Data 1', 'NDA240101AA1', 'Datos', 'https://example.com/nd1', 'https://example.com/logos/nd1.png', 'contacto4@example.com', '3331000104', (SELECT cve_ubicacion FROM ubicacion WHERE municipio = 'Guadalajara' LIMIT 1), 'nacional'),
('Nacional Data 2 S.A. de C.V.', 'Nacional Data 2', 'NDA240101AA2', 'Datos', 'https://example.com/nd2', 'https://example.com/logos/nd2.png', 'contacto5@example.com', '3331000105', (SELECT cve_ubicacion FROM ubicacion WHERE municipio = 'Guadalajara' LIMIT 1), 'nacional')
ON CONFLICT (razon_social) DO NOTHING;

INSERT INTO solicitud_convenio (cve_empresa, motivo, origen, estado, observacion)
SELECT cve_empresa, 'Solicitud de convenio de prueba', 'seed', 'pendiente', 'Registro seed'
FROM empresa
ORDER BY cve_empresa
LIMIT 2
ON CONFLICT DO NOTHING;

INSERT INTO pregunta (cve_prueba, texto, tipo_pregunta, orden, ponderacion)
SELECT p.cve_prueba, 'Pregunta ' || gs.n || ' de ' || tp.categoria, 'opcion_multiple', gs.n, 1
FROM prueba p
JOIN tipo_prueba tp ON tp.cve_tipo_prueba = p.cve_tipo_prueba
CROSS JOIN generate_series(1, 10) AS gs(n)
WHERE p.es_banco_general = true
ON CONFLICT (cve_prueba, orden) DO NOTHING;

INSERT INTO opcion_respuesta (cve_pregunta, texto, valor, es_correcta, orden)
SELECT q.cve_pregunta, 'Opción alta', 100, true, 1 FROM pregunta q
ON CONFLICT (cve_pregunta, orden) DO NOTHING;
INSERT INTO opcion_respuesta (cve_pregunta, texto, valor, es_correcta, orden)
SELECT q.cve_pregunta, 'Opción media', 60, false, 2 FROM pregunta q
ON CONFLICT (cve_pregunta, orden) DO NOTHING;
INSERT INTO opcion_respuesta (cve_pregunta, texto, valor, es_correcta, orden)
SELECT q.cve_pregunta, 'Opción baja', 20, false, 3 FROM pregunta q
ON CONFLICT (cve_pregunta, orden) DO NOTHING;

INSERT INTO vacante (cve_empresa, titulo, descripcion, area, cve_ubicacion, modalidad, salario_minimo, salario_maximo, estado)
SELECT e.cve_empresa, 'Desarrollador Web Junior ' || row_number() over (), 'Desarrollo de aplicaciones con PHP, Angular y PostgreSQL', 'Tecnologías de la información',
       e.cve_ubicacion, 'hibrido', 10000, 15000, 'publicada'
FROM empresa e
ORDER BY e.cve_empresa
LIMIT 5
ON CONFLICT DO NOTHING;

INSERT INTO perfil_idoneo (cve_vacante, puntaje_psicometrica, puntaje_cognitiva, puntaje_tecnica, puntaje_proyectiva)
SELECT cve_vacante, 75, 80, 85, 70
FROM vacante
ON CONFLICT (cve_vacante) DO NOTHING;

INSERT INTO fuente_api (nombre, url_base, descripcion)
VALUES ('Seed Nacional', 'local://seed', 'Vacantes nacionales mock')
ON CONFLICT (nombre) DO UPDATE SET url_base = EXCLUDED.url_base;

INSERT INTO vacante_api (cve_fuente_api, id_externo, titulo, descripcion, empresa_externa, ubicacion_texto, modalidad, url_original, datos_originales)
SELECT f.cve_fuente_api, 'seed-nacional-' || n, 'Vacante nacional ' || n, 'Vacante nacional de prueba', 'Empresa Nacional ' || n, 'México', 'remoto', 'https://example.com/jobs/' || n, '{}'::jsonb
FROM fuente_api f
CROSS JOIN generate_series(1, 3) n
WHERE f.nombre = 'Seed Nacional'
ON CONFLICT (cve_fuente_api, id_externo) DO NOTHING;

INSERT INTO evaluacion (cve_egresado, cve_prueba, estado, fecha_inicio, fecha_finalizacion, puntaje_obtenido, observacion)
SELECT e.cve_egresado, p.cve_prueba, 'finalizada', now() - interval '2 days', now() - interval '1 day',
       CASE tp.categoria
           WHEN 'psicometrica' THEN 82
           WHEN 'cognitiva' THEN 84
           WHEN 'tecnica' THEN 88
           ELSE 78
       END,
       'Evaluación seed finalizada'
FROM (SELECT cve_egresado FROM egresado ORDER BY cve_egresado LIMIT 3) e
CROSS JOIN prueba p
JOIN tipo_prueba tp ON tp.cve_tipo_prueba = p.cve_tipo_prueba
WHERE p.es_banco_general = true
ON CONFLICT DO NOTHING;

INSERT INTO postulacion (cve_egresado, cve_vacante)
SELECT e.cve_egresado, v.cve_vacante
FROM (SELECT cve_egresado, row_number() over () AS rn FROM egresado ORDER BY cve_egresado LIMIT 3) e
JOIN (SELECT cve_vacante, row_number() over () AS rn FROM vacante WHERE estado = 'publicada' ORDER BY cve_vacante LIMIT 3) v
  ON v.rn = e.rn
ON CONFLICT (cve_egresado, cve_vacante) DO NOTHING;

INSERT INTO contratacion (cve_postulacion, puesto, salario_contratado, observacion)
SELECT cve_postulacion, 'Desarrollador Web Junior', 14000, 'Contratación seed'
FROM postulacion
ORDER BY cve_postulacion
LIMIT 1
ON CONFLICT (cve_postulacion) DO NOTHING;

INSERT INTO mensaje (cve_postulacion, tipo_emisor, mensaje)
SELECT cve_postulacion, 'empresa', 'Nos interesa iniciar una entrevista contigo.'
FROM postulacion
WHERE porcentaje_coincidencia >= 80
ORDER BY cve_postulacion
LIMIT 2
ON CONFLICT DO NOTHING;
