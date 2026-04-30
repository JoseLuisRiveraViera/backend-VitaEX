-- Migración para el sistema de encuestas dinámicas
SET search_path TO bolsa_trabajo;

-- 1. Asegurar que la tabla prueba tenga cve_carrera
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='prueba' AND column_name='cve_carrera') THEN
        ALTER TABLE prueba ADD COLUMN cve_carrera INTEGER REFERENCES carrera(cve_carrera);
    END IF;
END $$;

-- 2. Crear tabla para resultados globales si no existe
CREATE TABLE IF NOT EXISTS resultado_global_egresado (
    cve_resultado_global SERIAL PRIMARY KEY,
    cve_egresado INTEGER REFERENCES egresado(cve_egresado) ON DELETE CASCADE,
    puntaje_psicometrica DECIMAL(5,2) DEFAULT 0,
    puntaje_cognitiva DECIMAL(5,2) DEFAULT 0,
    puntaje_tecnica DECIMAL(5,2) DEFAULT 0,
    puntaje_proyectiva DECIMAL(5,2) DEFAULT 0,
    puntaje_global DECIMAL(5,2) DEFAULT 0,
    nivel_interpretacion VARCHAR(50),
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(cve_egresado)
);

-- 3. Limpiar preguntas de prueba anteriores (opcional, para el demo)
-- DELETE FROM opcion_respuesta WHERE cve_pregunta IN (SELECT cve_pregunta FROM pregunta WHERE cve_prueba IN (SELECT cve_prueba FROM prueba WHERE es_banco_general = true));
-- DELETE FROM pregunta WHERE cve_prueba IN (SELECT cve_prueba FROM prueba WHERE es_banco_general = true);

-- 4. Insertar Pruebas por Carrera
-- TI (Tecnologías de la Información)
INSERT INTO prueba (cve_tipo_prueba, cve_carrera, nombre, descripcion, es_banco_general)
SELECT cve_tipo_prueba, (SELECT cve_carrera FROM carrera WHERE clave_oficial = 'TI'), 
       'Evaluación ' || nombre || ' - TI', 'Banco de preguntas para TI', true
FROM tipo_prueba
ON CONFLICT DO NOTHING;

-- PAL (Procesos Alimentarios)
INSERT INTO prueba (cve_tipo_prueba, cve_carrera, nombre, descripcion, es_banco_general)
SELECT cve_tipo_prueba, (SELECT cve_carrera FROM carrera WHERE clave_oficial = 'PAL'), 
       'Evaluación ' || nombre || ' - PAL', 'Banco de preguntas para PAL', true
FROM tipo_prueba
ON CONFLICT DO NOTHING;

-- 5. Función auxiliar para insertar preguntas y opciones Likert
-- (Esto se haría vía script o múltiples inserts manuales)
-- Insertaremos 10 reactivos por categoría para TI y PAL

-- Ejemplo para TI - Técnica
DO $$
DECLARE
    ti_carrera_id INT;
    prueba_id INT;
    pregunta_id INT;
    i INT;
    preguntas_ti_tecnica TEXT[] := ARRAY[
        '¿Qué tan familiarizado estás con el diseño de bases de datos relacionales?',
        '¿Cuál es tu nivel de conocimiento en el desarrollo de APIs RESTful?',
        '¿Qué tan cómodo te sientes utilizando sistemas de control de versiones como Git?',
        '¿Qué tanto conoces sobre los principios de seguridad en aplicaciones web (OWASP)?',
        '¿Cuál es tu experiencia trabajando con frameworks de Frontend (Angular, React, Vue)?',
        '¿Qué tanto dominas el despliegue de aplicaciones en servicios de nube (AWS, Azure, GCP)?',
        '¿Qué tan capaz eres de optimizar consultas SQL complejas?',
        '¿Cuál es tu conocimiento sobre contenedores (Docker, Kubernetes)?',
        '¿Qué tan habituado estás a realizar pruebas unitarias y de integración?',
        '¿Qué tanto aplicas metodologías ágiles (Scrum, Kanban) en tus proyectos?'
    ];
BEGIN
    SELECT cve_carrera INTO ti_carrera_id FROM carrera WHERE clave_oficial = 'TI';
    SELECT cve_prueba INTO prueba_id FROM prueba WHERE cve_carrera = ti_carrera_id AND cve_tipo_prueba = (SELECT cve_tipo_prueba FROM tipo_prueba WHERE categoria = 'tecnica');

    FOR i IN 1..10 LOOP
        INSERT INTO pregunta (cve_prueba, texto, tipo_pregunta, orden, ponderacion)
        VALUES (prueba_id, preguntas_ti_tecnica[i], 'opcion_multiple', i, 1)
        RETURNING cve_pregunta INTO pregunta_id;

        -- Opciones Likert 1-5
        INSERT INTO opcion_respuesta (cve_pregunta, texto, valor, es_correcta, orden) VALUES
        (pregunta_id, 'Totalmente en desacuerdo / Nulo', 1, false, 1),
        (pregunta_id, 'En desacuerdo / Bajo', 2, false, 2),
        (pregunta_id, 'Neutral / Medio', 3, false, 3),
        (pregunta_id, 'De acuerdo / Alto', 4, false, 4),
        (pregunta_id, 'Totalmente de acuerdo / Experto', 5, true, 5);
    END LOOP;
END $$;

-- 6. Reagentes para PAL - Técnica
DO $$
DECLARE
    pal_carrera_id INT;
    prueba_id INT;
    pregunta_id INT;
    i INT;
    preguntas_pal_tecnica TEXT[] := ARRAY[
        '¿Qué tanto conoces sobre las normas de inocuidad alimentaria (HACCP)?',
        '¿Cuál es tu nivel de experiencia en técnicas de conservación de alimentos?',
        '¿Qué tan familiarizado estás con los procesos de control de calidad en planta?',
        '¿Qué tanto dominas el uso de maquinaria para el procesamiento de alimentos?',
        '¿Cuál es tu conocimiento sobre microbiología aplicada a alimentos?',
        '¿Qué tan capaz eres de diseñar un plan de gestión de residuos alimentarios?',
        '¿Qué tanto conoces sobre el etiquetado y normatividad vigente?',
        '¿Cuál es tu experiencia en el desarrollo de nuevos productos alimenticios?',
        '¿Qué tan habituado estás a realizar análisis sensoriales?',
        '¿Qué tanto conoces sobre la logística y cadena de frío?'
    ];
BEGIN
    SELECT cve_carrera INTO pal_carrera_id FROM carrera WHERE clave_oficial = 'PAL';
    SELECT cve_prueba INTO prueba_id FROM prueba WHERE cve_carrera = pal_carrera_id AND cve_tipo_prueba = (SELECT cve_tipo_prueba FROM tipo_prueba WHERE categoria = 'tecnica');

    FOR i IN 1..10 LOOP
        INSERT INTO pregunta (cve_prueba, texto, tipo_pregunta, orden, ponderacion)
        VALUES (prueba_id, preguntas_pal_tecnica[i], 'opcion_multiple', i, 1)
        RETURNING cve_pregunta INTO pregunta_id;

        INSERT INTO opcion_respuesta (cve_pregunta, texto, valor, es_correcta, orden) VALUES
        (pregunta_id, 'Totalmente en desacuerdo', 1, false, 1),
        (pregunta_id, 'En desacuerdo', 2, false, 2),
        (pregunta_id, 'Neutral', 3, false, 3),
        (pregunta_id, 'De acuerdo', 4, false, 4),
        (pregunta_id, 'Totalmente de acuerdo', 5, true, 5);
    END LOOP;
END $$;

-- 7. Reagentes para TI - Psicométrica (Personalidad Laboral)
DO $$
DECLARE
    ti_carrera_id INT;
    prueba_id INT;
    pregunta_id INT;
    i INT;
    preguntas_ti_psicometrica TEXT[] := ARRAY[
        'Me gusta trabajar bajo presión en entornos dinámicos.',
        'Suelo tomar la iniciativa ante problemas inesperados.',
        'Prefiero trabajar en equipo que de forma individual.',
        'Mantengo la calma incluso en situaciones de alta tensión.',
        'Soy capaz de adaptarme rápidamente a nuevas tecnologías.',
        'Considero que la organización es la clave para entregar a tiempo.',
        'Me siento cómodo comunicando ideas técnicas a perfiles no técnicos.',
        'Busco activamente aprender nuevas habilidades por mi cuenta.',
        'Acepto las críticas constructivas para mejorar mi desempeño.',
        'Soy capaz de liderar grupos de trabajo de manera efectiva.'
    ];
BEGIN
    SELECT cve_carrera INTO ti_carrera_id FROM carrera WHERE clave_oficial = 'TI';
    SELECT cve_prueba INTO prueba_id FROM prueba WHERE cve_carrera = ti_carrera_id AND cve_tipo_prueba = (SELECT cve_tipo_prueba FROM tipo_prueba WHERE categoria = 'psicometrica');

    FOR i IN 1..10 LOOP
        INSERT INTO pregunta (cve_prueba, texto, tipo_pregunta, orden, ponderacion)
        VALUES (prueba_id, preguntas_ti_psicometrica[i], 'opcion_multiple', i, 1)
        RETURNING cve_pregunta INTO pregunta_id;

        INSERT INTO opcion_respuesta (cve_pregunta, texto, valor, es_correcta, orden) VALUES
        (pregunta_id, 'Totalmente en desacuerdo', 1, false, 1),
        (pregunta_id, 'En desacuerdo', 2, false, 2),
        (pregunta_id, 'Neutral', 3, false, 3),
        (pregunta_id, 'De acuerdo', 4, false, 4),
        (pregunta_id, 'Totalmente de acuerdo', 5, true, 5);
    END LOOP;
END $$;

-- 8. Reagentes para TI - Cognitiva (Potencial de Aprendizaje)
DO $$
DECLARE
    ti_carrera_id INT;
    prueba_id INT;
    pregunta_id INT;
    i INT;
    preguntas_ti_cognitiva TEXT[] := ARRAY[
        'Soy capaz de identificar patrones lógicos en sistemas complejos.',
        'Resuelvo problemas matemáticos con facilidad.',
        'Puedo comprender rápidamente una nueva documentación técnica.',
        'Tengo facilidad para memorizar comandos y sintaxis.',
        'Analizo todas las variables antes de tomar una decisión.',
        'Comprendo diagramas de flujo de datos sin dificultad.',
        'Tengo facilidad para el pensamiento abstracto.',
        'Puedo concentrarme durante largos periodos de tiempo.',
        'Identifico rápidamente errores de sintaxis en el código.',
        'Tengo buena memoria a corto plazo para tareas concurrentes.'
    ];
BEGIN
    SELECT cve_carrera INTO ti_carrera_id FROM carrera WHERE clave_oficial = 'TI';
    SELECT cve_prueba INTO prueba_id FROM prueba WHERE cve_carrera = ti_carrera_id AND cve_tipo_prueba = (SELECT cve_tipo_prueba FROM tipo_prueba WHERE categoria = 'cognitiva');

    FOR i IN 1..10 LOOP
        INSERT INTO pregunta (cve_prueba, texto, tipo_pregunta, orden, ponderacion)
        VALUES (prueba_id, preguntas_ti_cognitiva[i], 'opcion_multiple', i, 1)
        RETURNING cve_pregunta INTO pregunta_id;

        INSERT INTO opcion_respuesta (cve_pregunta, texto, valor, es_correcta, orden) VALUES
        (pregunta_id, 'Muy bajo', 1, false, 1),
        (pregunta_id, 'Bajo', 2, false, 2),
        (pregunta_id, 'Medio', 3, false, 3),
        (pregunta_id, 'Alto', 4, false, 4),
        (pregunta_id, 'Muy alto', 5, true, 5);
    END LOOP;
END $$;

-- 9. Reagentes para TI - Proyectiva / SJT (Juicio Situacional)
DO $$
DECLARE
    ti_carrera_id INT;
    prueba_id INT;
    pregunta_id INT;
    i INT;
    preguntas_ti_proyectiva TEXT[] := ARRAY[
        'Si un compañero borra accidentalmente código crítico, ¿qué harías?',
        'Si el cliente pide un cambio mayor un día antes de la entrega, ¿cómo reaccionas?',
        'Si detectas una vulnerabilidad de seguridad en un proyecto ajeno, ¿qué haces?',
        'Si tu líder te asigna una tarea con una tecnología que no conoces, ¿cómo procedes?',
        'Si hay un conflicto ético sobre los datos del usuario, ¿cuál es tu postura?',
        'Si un sistema falla en producción un viernes a las 6 PM, ¿cómo actúas?',
        'Si crees que el diseño de un colega es ineficiente, ¿cómo se lo comunicas?',
        'Si tienes que elegir entre calidad de código y rapidez de entrega, ¿qué priorizas?',
        'Si no logras entender un bug después de 4 horas, ¿cuál es tu siguiente paso?',
        'Si recibes feedback negativo injustificado de un superior, ¿qué haces?'
    ];
BEGIN
    SELECT cve_carrera INTO ti_carrera_id FROM carrera WHERE clave_oficial = 'TI';
    SELECT cve_prueba INTO prueba_id FROM prueba WHERE cve_carrera = ti_carrera_id AND cve_tipo_prueba = (SELECT cve_tipo_prueba FROM tipo_prueba WHERE categoria = 'proyectiva');

    FOR i IN 1..10 LOOP
        INSERT INTO pregunta (cve_prueba, texto, tipo_pregunta, orden, ponderacion)
        VALUES (prueba_id, preguntas_ti_proyectiva[i], 'opcion_multiple', i, 1)
        RETURNING cve_pregunta INTO pregunta_id;

        INSERT INTO opcion_respuesta (cve_pregunta, texto, valor, es_correcta, orden) VALUES
        (pregunta_id, 'Respuesta inmadura', 1, false, 1),
        (pregunta_id, 'Respuesta pasiva', 2, false, 2),
        (pregunta_id, 'Respuesta estándar', 3, false, 3),
        (pregunta_id, 'Respuesta proactiva', 4, false, 4),
        (pregunta_id, 'Respuesta ejemplar', 5, true, 5);
    END LOOP;
END $$;

-- (Repetir lógica similar para PAL Psicométrica, Cognitiva y Proyectiva si es necesario)
-- Por ahora se deja TI completo y PAL Técnica.

-- 10. Actualizar vista de puntajes
CREATE OR REPLACE VIEW vw_puntaje_egresado AS
SELECT 
    cve_egresado,
    puntaje_psicometrica,
    puntaje_cognitiva,
    puntaje_tecnica,
    puntaje_proyectiva,
    puntaje_global,
    nivel_interpretacion
FROM resultado_global_egresado;
