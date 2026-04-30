-- Catalogo de preguntas por carrera para evaluaciones VitaeX.
-- Ejecutar con:
-- psql -h localhost -U postgres -d bolsa_trabajo -f database/catalogo_preguntas_carreras.sql

SET search_path TO bolsa_trabajo;

ALTER TABLE prueba
ADD COLUMN IF NOT EXISTS cve_carrera BIGINT REFERENCES carrera(cve_carrera);

CREATE INDEX IF NOT EXISTS idx_prueba_cve_carrera ON prueba(cve_carrera);

CREATE OR REPLACE FUNCTION cargar_catalogo_evaluacion(
	p_clave_carrera TEXT,
	p_categoria TEXT,
	p_nombre_prueba TEXT,
	p_descripcion TEXT,
	p_opciones TEXT[],
	p_reactivos TEXT[]
) RETURNS VOID AS $$
DECLARE
	v_carrera BIGINT;
	v_tipo_prueba BIGINT;
	v_prueba BIGINT;
	v_pregunta BIGINT;
	i INT;
	j INT;
BEGIN
	SELECT cve_carrera INTO v_carrera
	FROM carrera
	WHERE clave_oficial = p_clave_carrera;

	IF v_carrera IS NULL THEN
		RAISE EXCEPTION 'No existe la carrera con clave_oficial=%', p_clave_carrera;
	END IF;

	SELECT cve_tipo_prueba INTO v_tipo_prueba
	FROM tipo_prueba
	WHERE categoria::TEXT = p_categoria;

	IF v_tipo_prueba IS NULL THEN
		RAISE EXCEPTION 'No existe tipo_prueba con categoria=%', p_categoria;
	END IF;

	SELECT cve_prueba INTO v_prueba
	FROM prueba
	WHERE cve_tipo_prueba = v_tipo_prueba
	  AND cve_carrera = v_carrera
	  AND es_banco_general = TRUE
	ORDER BY cve_prueba
	LIMIT 1;

	IF v_prueba IS NULL THEN
		INSERT INTO prueba (
			cve_tipo_prueba,
			cve_carrera,
			nombre,
			descripcion,
			instrucciones,
			puntaje_minimo_aprobatorio,
			es_banco_general,
			estado
		)
		VALUES (
			v_tipo_prueba,
			v_carrera,
			p_nombre_prueba,
			p_descripcion,
			'Responde cada reactivo usando la escala cuantitativa de 1 a 5.',
			0,
			TRUE,
			'activo'
		)
		RETURNING cve_prueba INTO v_prueba;
	ELSE
		UPDATE prueba
		SET nombre = p_nombre_prueba,
			descripcion = p_descripcion,
			instrucciones = 'Responde cada reactivo usando la escala cuantitativa de 1 a 5.',
			estado = 'activo',
			fecha_modificacion = CURRENT_TIMESTAMP
		WHERE cve_prueba = v_prueba;
	END IF;

	DELETE FROM pregunta WHERE cve_prueba = v_prueba;

	FOR i IN 1..array_length(p_reactivos, 1) LOOP
		INSERT INTO pregunta (cve_prueba, texto, tipo_pregunta, orden, ponderacion, estado)
		VALUES (v_prueba, p_reactivos[i], 'opcion_multiple', i, 1, 'activo')
		RETURNING cve_pregunta INTO v_pregunta;

		FOR j IN 1..array_length(p_opciones, 1) LOOP
			INSERT INTO opcion_respuesta (cve_pregunta, texto, valor, es_correcta, orden)
			VALUES (v_pregunta, p_opciones[j], j, j = 5, j);
		END LOOP;
	END LOOP;
END;
$$ LANGUAGE plpgsql;

SELECT cargar_catalogo_evaluacion(
	'TI',
	'psicometrica',
	'Evaluacion psicometrica - Tecnologias de la Informacion',
	'Medir personalidad laboral, comportamiento bajo presion y trabajo en equipo.',
	ARRAY[
		'Totalmente en desacuerdo',
		'En desacuerdo',
		'Neutral',
		'De acuerdo',
		'Totalmente de acuerdo'
	],
	ARRAY[
		'Entrego mis actividades en la fecha establecida.',
		'Reviso cuidadosamente mi trabajo antes de entregarlo.',
		'Me adapto facilmente a nuevas herramientas tecnologicas.',
		'Mantengo la calma cuando un sistema presenta errores.',
		'Acepto retroalimentacion para mejorar mi trabajo.',
		'Me gusta colaborar con otras personas en proyectos.',
		'Puedo comunicar mis ideas tecnicas de manera clara.',
		'Me organizo bien cuando tengo varias tareas pendientes.',
		'Busco soluciones creativas ante problemas tecnicos.',
		'Mantengo una actitud profesional cuando trabajo bajo presion.'
	]
);

SELECT cargar_catalogo_evaluacion(
	'TI',
	'cognitiva',
	'Evaluacion cognitiva - Tecnologias de la Informacion',
	'Medir potencial de aprendizaje, agilidad mental y razonamiento logico-verbal.',
	ARRAY['Muy bajo', 'Bajo', 'Medio', 'Alto', 'Muy alto'],
	ARRAY[
		'Identifico patrones en problemas logicos.',
		'Puedo resolver problemas nuevos sin recibir instrucciones completas.',
		'Comprendo diagramas, tablas o estructuras de informacion.',
		'Aprendo rapidamente el funcionamiento de una nueva plataforma.',
		'Puedo analizar varias posibles soluciones antes de decidir.',
		'Se me facilita interpretar instrucciones tecnicas.',
		'Puedo detectar errores en una secuencia de pasos.',
		'Comprendo textos tecnicos relacionados con software o sistemas.',
		'Puedo resolver problemas basicos de logica matematica.',
		'Trabajo bien cuando tengo poco tiempo para resolver un problema.'
	]
);

SELECT cargar_catalogo_evaluacion(
	'TI',
	'tecnica',
	'Evaluacion tecnica - Tecnologias de la Informacion',
	'Validar conocimientos especificos del area de Tecnologias de la Informacion.',
	ARRAY['No lo domino', 'Nivel bajo', 'Nivel medio', 'Nivel alto', 'Nivel avanzado'],
	ARRAY[
		'Programacion basica.',
		'Desarrollo web.',
		'Manejo de bases de datos.',
		'Uso de Git o GitHub.',
		'Consumo de APIs.',
		'Resolucion de errores de software.',
		'Seguridad informatica basica.',
		'Diseno de interfaces de usuario.',
		'Documentacion tecnica.',
		'Analisis de requerimientos de software.'
	]
);

SELECT cargar_catalogo_evaluacion(
	'TI',
	'proyectiva',
	'Evaluacion proyectiva SJT - Tecnologias de la Informacion',
	'Identificar estabilidad emocional, juicio laboral y reaccion ante situaciones reales.',
	ARRAY['Muy inadecuada', 'Inadecuada', 'Regular', 'Adecuada', 'Muy adecuada'],
	ARRAY[
		'Si mi codigo falla antes de una entrega, reviso el error, identifico la causa y documento la solucion.',
		'Si no entiendo un requerimiento, pregunto antes de desarrollar algo incorrecto.',
		'Si un companero comete un error en el proyecto, lo apoyo y revisamos juntos la solucion.',
		'Si encuentro una vulnerabilidad en el sistema, la reporto al responsable.',
		'Si el proyecto se atrasa, ayudo a priorizar las funciones mas importantes.',
		'Si recibo una critica sobre mi trabajo, la tomo como oportunidad de mejora.',
		'Si una tarea es urgente, mantengo la calma y organizo los pasos a seguir.',
		'Si un usuario reporta un problema, escucho, registro el caso y busco una solucion.',
		'Si detecto informacion sensible expuesta, evito compartirla y aviso al equipo.',
		'Si tengo dudas sobre una tecnologia, investigo antes de improvisar.'
	]
);

SELECT cargar_catalogo_evaluacion(
	'PAL',
	'psicometrica',
	'Evaluacion psicometrica - Procesos Alimentarios',
	'Medir personalidad laboral, comportamiento bajo presion y trabajo en equipo.',
	ARRAY[
		'Totalmente en desacuerdo',
		'En desacuerdo',
		'Neutral',
		'De acuerdo',
		'Totalmente de acuerdo'
	],
	ARRAY[
		'Sigo instrucciones de produccion con cuidado.',
		'Mantengo limpia y ordenada mi area de trabajo.',
		'Cumplo con los tiempos establecidos en mis actividades.',
		'Me adapto facilmente a nuevos procesos alimentarios.',
		'Mantengo la calma cuando hay presion en produccion.',
		'Trabajo bien con otras personas.',
		'Acepto correcciones para mejorar mi desempeno.',
		'Respeto las normas de higiene y seguridad.',
		'Soy cuidadoso al revisar la calidad de un producto.',
		'Mantengo una actitud responsable al manipular alimentos.'
	]
);

SELECT cargar_catalogo_evaluacion(
	'PAL',
	'cognitiva',
	'Evaluacion cognitiva - Procesos Alimentarios',
	'Medir potencial de aprendizaje, agilidad mental y razonamiento logico-verbal.',
	ARRAY['Muy bajo', 'Bajo', 'Medio', 'Alto', 'Muy alto'],
	ARRAY[
		'Comprendo instrucciones tecnicas relacionadas con procesos alimentarios.',
		'Puedo identificar errores en una secuencia de produccion.',
		'Se me facilita aprender nuevos procedimientos de trabajo.',
		'Puedo analizar la causa de un producto defectuoso.',
		'Comprendo tablas de temperatura, tiempo o cantidades.',
		'Puedo resolver problemas basicos de calculo de ingredientes.',
		'Identifico patrones en fallas de produccion.',
		'Puedo tomar decisiones rapidas ante un problema de calidad.',
		'Comprendo normas basicas de higiene e inocuidad.',
		'Puedo explicar un procedimiento alimentario de forma clara.'
	]
);

SELECT cargar_catalogo_evaluacion(
	'PAL',
	'tecnica',
	'Evaluacion tecnica - Procesos Alimentarios',
	'Validar conocimientos especificos del area de Procesos Alimentarios.',
	ARRAY['No lo domino', 'Nivel bajo', 'Nivel medio', 'Nivel alto', 'Nivel avanzado'],
	ARRAY[
		'Buenas practicas de higiene.',
		'Manejo seguro de alimentos.',
		'Control de temperaturas.',
		'Limpieza y sanitizacion de areas.',
		'Uso correcto de equipo de produccion.',
		'Manejo de materia prima.',
		'Control de calidad del producto.',
		'Identificacion de riesgos de contaminacion.',
		'Registro de tiempos, cantidades y temperaturas.',
		'Elaboracion de reportes de produccion.'
	]
);

SELECT cargar_catalogo_evaluacion(
	'PAL',
	'proyectiva',
	'Evaluacion proyectiva SJT - Procesos Alimentarios',
	'Identificar rasgos profundos, estabilidad emocional y juicio ante situaciones laborales.',
	ARRAY['Muy inadecuada', 'Inadecuada', 'Regular', 'Adecuada', 'Muy adecuada'],
	ARRAY[
		'Si un alimento estuvo fuera de temperatura segura, lo reporto y evito usarlo hasta verificar su estado.',
		'Si un producto tiene olor, color o textura diferente, reviso materia prima, proceso y almacenamiento.',
		'Si un companero no sigue una norma de higiene, lo corrijo de forma respetuosa.',
		'Si una maquina presenta fallas, detengo su uso si representa riesgo y aviso al responsable.',
		'Si hay mucha merma en una produccion, registro el problema y reviso posibles causas.',
		'Si recibo una correccion sobre mi tecnica, la aplico para mejorar la calidad del producto.',
		'Si falta materia prima, aviso antes de alterar la formula o receta.',
		'Si detecto contaminacion cruzada, detengo el proceso y reporto la situacion.',
		'Si hay presion por terminar rapido, mantengo las normas de higiene aunque tome mas tiempo.',
		'Si un lote no cumple con la calidad esperada, evito liberarlo sin autorizacion.'
	]
);

DROP FUNCTION cargar_catalogo_evaluacion(TEXT, TEXT, TEXT, TEXT, TEXT[], TEXT[]);
