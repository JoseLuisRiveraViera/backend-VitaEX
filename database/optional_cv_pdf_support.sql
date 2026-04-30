-- Este archivo contiene la estructura opcional que podría faltar
-- para que el módulo de generación de CV en PDF funcione al 100%.

SET search_path TO bolsa_trabajo;

CREATE TABLE IF NOT EXISTS experiencia_laboral (
    cve_experiencia_laboral SERIAL PRIMARY KEY,
    cve_egresado INT NOT NULL,
    empresa VARCHAR(255) NOT NULL,
    puesto VARCHAR(255) NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE,
    descripcion TEXT,
    estado VARCHAR(50) DEFAULT 'activo'
);

CREATE TABLE IF NOT EXISTS habilidad (
    cve_habilidad SERIAL PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS egresado_habilidad (
    cve_egresado INT NOT NULL,
    cve_habilidad INT NOT NULL,
    nivel VARCHAR(50) NOT NULL, -- e.g., Básico, Intermedio, Avanzado
    PRIMARY KEY (cve_egresado, cve_habilidad)
);

CREATE TABLE IF NOT EXISTS documento_egresado (
    cve_documento_egresado SERIAL PRIMARY KEY,
    cve_egresado INT NOT NULL,
    nombre_documento VARCHAR(255) NOT NULL,
    fecha_emision DATE,
    url_documento VARCHAR(255)
);

-- Note: Other tables like egresado, carrera, vacante, evaluacion,
-- tipo_prueba, prueba, etc. are assumed to exist as they are in seed.sql.
