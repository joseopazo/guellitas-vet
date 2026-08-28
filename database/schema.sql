
-- Guellitas Vet — Esquema de base de datos (MySQL 8.0)
-- Trece tablas normalizadas a 3FN + control de traslapes de horas.
-- Generado a partir del diccionario de datos del Capítulo VI (6.3).

CREATE DATABASE IF NOT EXISTS guellitas_vet
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE guellitas_vet;

SET FOREIGN_KEY_CHECKS = 0;


-- Grupo: Seguridad y acceso


CREATE TABLE rol (
  id_rol      INT AUTO_INCREMENT PRIMARY KEY,
  nombre_rol  VARCHAR(30)  NOT NULL UNIQUE,
  descripcion VARCHAR(150) NULL
) ENGINE=InnoDB;

CREATE TABLE usuario (
  id_usuario     INT AUTO_INCREMENT PRIMARY KEY,
  id_rol         INT NOT NULL,
  rut            VARCHAR(12)  NOT NULL UNIQUE,
  nombre         VARCHAR(50)  NOT NULL,
  apellido       VARCHAR(50)  NOT NULL,
  email          VARCHAR(120) NOT NULL UNIQUE,
  password_hash  VARCHAR(255) NOT NULL,
  telefono       VARCHAR(15)  NULL,
  estado         TINYINT(1)   NOT NULL DEFAULT 1,
  fecha_registro DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_usuario_rol FOREIGN KEY (id_rol) REFERENCES rol(id_rol)
) ENGINE=InnoDB;


-- Grupo: Catálogos de referencia


CREATE TABLE especie (
  id_especie     INT AUTO_INCREMENT PRIMARY KEY,
  nombre_especie VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE raza (
  id_raza     INT AUTO_INCREMENT PRIMARY KEY,
  id_especie  INT NOT NULL,
  nombre_raza VARCHAR(40) NOT NULL,
  CONSTRAINT fk_raza_especie FOREIGN KEY (id_especie) REFERENCES especie(id_especie)
) ENGINE=InnoDB;

CREATE TABLE tipo_atencion (
  id_tipo_atencion INT AUTO_INCREMENT PRIMARY KEY,
  nombre_tipo      VARCHAR(60) NOT NULL UNIQUE,
  duracion_min     INT NOT NULL,
  valor            INT NOT NULL
) ENGINE=InnoDB;

CREATE TABLE estado_cita (
  id_estado_cita INT AUTO_INCREMENT PRIMARY KEY,
  nombre_estado  VARCHAR(20) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE vacuna (
  id_vacuna           INT AUTO_INCREMENT PRIMARY KEY,
  id_especie          INT NOT NULL,
  nombre_vacuna       VARCHAR(60) NOT NULL,
  periodicidad_meses  INT NOT NULL,
  CONSTRAINT fk_vacuna_especie FOREIGN KEY (id_especie) REFERENCES especie(id_especie)
) ENGINE=InnoDB;


-- Grupo: Núcleo operativo


CREATE TABLE mascota (
  id_mascota       INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario       INT NOT NULL,
  id_raza          INT NOT NULL,
  nombre           VARCHAR(40) NOT NULL,
  fecha_nacimiento DATE NULL,
  sexo             CHAR(1) NOT NULL,
  color            VARCHAR(30) NULL,
  num_chip         VARCHAR(20) NULL UNIQUE,
  estado           TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_mascota_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario),
  CONSTRAINT fk_mascota_raza    FOREIGN KEY (id_raza)    REFERENCES raza(id_raza),
  CONSTRAINT chk_mascota_sexo   CHECK (sexo IN ('M','H'))
) ENGINE=InnoDB;

CREATE TABLE horario_atencion (
  id_horario  INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario  INT NOT NULL,
  dia_semana  TINYINT NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fin    TIME NOT NULL,
  CONSTRAINT fk_horario_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario),
  CONSTRAINT chk_horario_dia    CHECK (dia_semana BETWEEN 1 AND 7),
  CONSTRAINT chk_horario_rango  CHECK (hora_fin > hora_inicio)
) ENGINE=InnoDB;

CREATE TABLE cita (
  id_cita           INT AUTO_INCREMENT PRIMARY KEY,
  id_mascota        INT NOT NULL,
  id_usuario        INT NOT NULL,
  id_tipo_atencion  INT NOT NULL,
  id_estado_cita    INT NOT NULL,
  fecha_hora_inicio DATETIME NOT NULL,
  fecha_hora_fin    DATETIME NOT NULL,
  motivo            VARCHAR(200) NULL,
  fecha_creacion    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cita_mascota  FOREIGN KEY (id_mascota)       REFERENCES mascota(id_mascota),
  CONSTRAINT fk_cita_usuario  FOREIGN KEY (id_usuario)       REFERENCES usuario(id_usuario),
  CONSTRAINT fk_cita_tipo     FOREIGN KEY (id_tipo_atencion) REFERENCES tipo_atencion(id_tipo_atencion),
  CONSTRAINT fk_cita_estado   FOREIGN KEY (id_estado_cita)   REFERENCES estado_cita(id_estado_cita),
  CONSTRAINT chk_cita_rango   CHECK (fecha_hora_fin > fecha_hora_inicio),
  -- Protección adicional de bajo costo (no reemplaza el trigger de traslape, ver más abajo)
  CONSTRAINT uq_cita_usuario_inicio UNIQUE (id_usuario, fecha_hora_inicio)
) ENGINE=InnoDB;

CREATE INDEX idx_cita_usuario_rango ON cita (id_usuario, fecha_hora_inicio, fecha_hora_fin);

CREATE TABLE ficha_clinica (
  id_ficha       INT AUTO_INCREMENT PRIMARY KEY,
  id_cita        INT NOT NULL UNIQUE,
  anamnesis      TEXT NULL,
  peso_kg        DECIMAL(5,2) NULL,
  temperatura_c  DECIMAL(4,1) NULL,
  diagnostico    TEXT NULL,
  tratamiento    TEXT NULL,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ficha_cita FOREIGN KEY (id_cita) REFERENCES cita(id_cita)
) ENGINE=InnoDB;


-- Grupo: Servicios de apoyo


CREATE TABLE mascota_vacuna (
  id_mascota_vacuna INT AUTO_INCREMENT PRIMARY KEY,
  id_mascota        INT NOT NULL,
  id_vacuna         INT NOT NULL,
  id_usuario        INT NOT NULL,
  fecha_aplicacion  DATE NOT NULL,
  fecha_proxima     DATE NULL,
  lote              VARCHAR(20) NULL,
  CONSTRAINT fk_mv_mascota FOREIGN KEY (id_mascota) REFERENCES mascota(id_mascota),
  CONSTRAINT fk_mv_vacuna  FOREIGN KEY (id_vacuna)  REFERENCES vacuna(id_vacuna),
  CONSTRAINT fk_mv_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
) ENGINE=InnoDB;

CREATE TABLE notificacion (
  id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario      INT NOT NULL,
  id_cita         INT NOT NULL,
  tipo            VARCHAR(20) NOT NULL,
  mensaje         VARCHAR(255) NOT NULL,
  fecha_envio     DATETIME NOT NULL,
  leida           TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_notif_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario),
  CONSTRAINT fk_notif_cita    FOREIGN KEY (id_cita)    REFERENCES cita(id_cita)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- Triggers de control de traslapes (Capítulo VII, apartado 7.2.1)


DELIMITER $$

CREATE TRIGGER trg_cita_valida_traslape_ins
BEFORE INSERT ON cita
FOR EACH ROW
BEGIN
  DECLARE conflictos INT;
  SELECT COUNT(*) INTO conflictos
  FROM cita c
  INNER JOIN estado_cita e ON e.id_estado_cita = c.id_estado_cita
  WHERE c.id_usuario = NEW.id_usuario
    AND e.nombre_estado <> 'Cancelada'
    AND NEW.fecha_hora_inicio < c.fecha_hora_fin
    AND NEW.fecha_hora_fin   > c.fecha_hora_inicio;

  IF conflictos > 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Traslape de horario para el profesional seleccionado';
  END IF;
END$$

CREATE TRIGGER trg_cita_valida_traslape_upd
BEFORE UPDATE ON cita
FOR EACH ROW
BEGIN
  DECLARE conflictos INT;
  SELECT COUNT(*) INTO conflictos
  FROM cita c
  INNER JOIN estado_cita e ON e.id_estado_cita = c.id_estado_cita
  WHERE c.id_usuario = NEW.id_usuario
    AND c.id_cita <> NEW.id_cita
    AND e.nombre_estado <> 'Cancelada'
    AND NEW.fecha_hora_inicio < c.fecha_hora_fin
    AND NEW.fecha_hora_fin   > c.fecha_hora_inicio;

  IF conflictos > 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Traslape de horario para el profesional seleccionado';
  END IF;
END$$

DELIMITER ;


-- Datos base (catálogos mínimos para poder probar el sistema)


INSERT INTO rol (nombre_rol, descripcion) VALUES
  ('Tutor', 'Dueño o responsable de una mascota; agenda y consulta citas'),
  ('Personal clínico', 'Veterinario o técnico; atiende citas y gestiona fichas clínicas');

INSERT INTO estado_cita (nombre_estado) VALUES
  ('Agendada'), ('Confirmada'), ('Atendida'), ('Cancelada'), ('No asistió');

INSERT INTO especie (nombre_especie) VALUES ('Canino'), ('Felino');

INSERT INTO raza (id_especie, nombre_raza) VALUES
  (1, 'Mestizo'), (1, 'Labrador'), (1, 'Poodle'),
  (2, 'Mestizo'), (2, 'Persa'), (2, 'Siames');

INSERT INTO tipo_atencion (nombre_tipo, duracion_min, valor) VALUES
  ('Consulta general', 30, 15000),
  ('Vacunación', 20, 12000),
  ('Control post-operatorio', 30, 10000),
  ('Urgencia', 45, 25000);

INSERT INTO vacuna (id_especie, nombre_vacuna, periodicidad_meses) VALUES
  (1, 'Séxtuple canina', 12),
  (1, 'Antirrábica', 12),
  (2, 'Triple felina', 12),
  (2, 'Antirrábica', 12);

-- Un usuario de cada rol para poder probar el login de inmediato.
-- La contraseña de ambos es "Password123" (hash bcrypt de ejemplo,
-- reemplázalo generando el tuyo con password_hash() en PHP).
INSERT INTO usuario (id_rol, rut, nombre, apellido, email, password_hash, telefono, estado) VALUES
  (1, '11111111-1', 'Tutor', 'De Prueba', 'tutor@correo.cl',
   '$2y$10$examplehashexamplehashexamplehashexamplehas', '+56900000001', 1),
  (2, '22222222-2', 'Vet', 'De Prueba', 'vet@correo.cl',
   '$2y$10$examplehashexamplehashexamplehashexamplehas', '+56900000002', 1);
