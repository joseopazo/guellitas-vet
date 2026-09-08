# Guellitas Vet

Aplicación web responsiva para la gestión de horas médicas y fichas clínicas electrónicas de una clínica veterinaria. Proyecto de Título — Carrera Analista Programador, IPLACEX.

**Autor:** José Ignacio Opazo López
**Docente guía:** Esteban Rautcher Rodrigues

## Problema que resuelve

La clínica gestiona su agendamiento por teléfono y cuaderno físico, y sus fichas clínicas en papel, lo que genera sobrecupos, pérdida de demanda fuera de horario y riesgo clínico por información fragmentada. Guellitas Vet permite a los tutores reservar horas en línea las 24 horas del día, y al personal clínico gestionar su disponibilidad y el historial electrónico de cada paciente.

## Estado actual del proyecto

- [x] Análisis, objetivos y requerimientos (16 funcionales, 12 no funcionales)
- [x] Modelo de datos: 13 tablas normalizadas a 3FN
- [x] Diagrama entidad-relación
- [x] Control de concurrencia y traslapes de horas (trigger de base de datos, validado)
- [x] Arquitectura del sistema definida (monolítica en capas / MVC)
- [x] Módulo de autenticación (registro, login, logout — probado contra la base de datos real)
- [x] Módulo de gestión de mascotas (alta, edición, activar/desactivar)
- [x] Módulo de agendamiento de citas (disponibilidad, reserva, reprogramación, anulación, con control de traslapes)
- [x] Módulo de ficha clínica (registro atómico y consulta de historial con alerta de alergias)
- [ ] Módulo de vacunas y recordatorios automáticos
- [ ] Recuperación de contraseña y edición de perfil
- [ ] Envío real de correos (SMTP) y reportes exportables
- [ ] Despliegue en hosting

## Stack tecnológico

- PHP 8.2
- MySQL 8.0
- HTML5, CSS3 (Flexbox / Grid, sin frameworks)
- JavaScript

## Estructura del repositorio

```
guellitas-vet/
├── database/
│   └── schema.sql        Esquema completo: 13 tablas + triggers de control de traslapes
├── public/                Punto de entrada de la aplicación (páginas PHP, CSS)
├── src/                   Configuración, DAO, helpers y excepciones de dominio
├── INSTRUCCIONES.txt      Cómo probar el avance actual en un entorno local (XAMPP)
└── README.md
```

## Cómo probar el modelo de datos

1. Instalar XAMPP (o cualquier entorno con MySQL/MariaDB 8.0+).
2. Crear una base de datos y ejecutar `database/schema.sql`.
3. El script crea las 13 tablas, dos triggers de control de traslape de horas, y datos base (roles, estados de cita, especies, razas, tipos de atención, vacunas).

## Corrección de diseño destacada

La restricción original para evitar sobrecupos (UNIQUE sobre usuario y hora de inicio) no detectaba traslapes entre citas de distinta duración. Se reemplazó por un trigger que valida solapamiento real de intervalos horarios (`fecha_hora_inicio` / `fecha_hora_fin`), verificado con casos de prueba donde la restricción original habría fallado.


## Cómo probar la aplicación (avance actual)

Ver `INSTRUCCIONES.txt` para el detalle completo. En resumen:

1. Copiar el repositorio dentro de tu servidor local (XAMPP u otro con PHP 8+ y MySQL/MariaDB).
2. Ejecutar `database/schema.sql` para crear el esquema.
3. Ajustar las credenciales de conexión en `src/config/config.php` si no usas la configuración por defecto de XAMPP.
4. Apuntar el servidor web a la carpeta `public/` (nunca a la raíz del repositorio).
5. Abrir `public/login.php` y crear una cuenta desde "Crear cuenta", o revisar `INSTRUCCIONES.txt` para más detalle sobre los usuarios de prueba.
