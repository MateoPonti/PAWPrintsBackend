-- PAWPrints · esquema de la base (PostgreSQL / Neon). Se ejecuta una vez en el SQL Editor de Neon
-- (o con psql). Es idempotente: se puede volver a correr sin romper datos.

-- Roles y permisos: un usuario tiene UN rol y un rol tiene VARIOS permisos. Los controladores piden permisos
-- (exigirPermiso('libros.crear')), no roles: un rol nuevo solo necesita filas en rol_permisos.
CREATE TABLE IF NOT EXISTS roles (
  id          INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  nombre      VARCHAR(20)  NOT NULL UNIQUE,
  descripcion VARCHAR(150) NOT NULL
);

CREATE TABLE IF NOT EXISTS permisos (
  id          INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  nombre      VARCHAR(50)  NOT NULL UNIQUE,   -- "recurso.accion"
  descripcion VARCHAR(150) NOT NULL
);

CREATE TABLE IF NOT EXISTS rol_permisos (
  rol_id     INTEGER NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
  permiso_id INTEGER NOT NULL REFERENCES permisos(id) ON DELETE CASCADE,
  PRIMARY KEY (rol_id, permiso_id)
);

INSERT INTO roles (nombre, descripcion) VALUES
  ('usuario',  'Cliente registrado'),
  ('personal', 'Personal de la librería: atiende los pedidos'),
  ('admin',    'Administra el catálogo y los usuarios')
ON CONFLICT (nombre) DO NOTHING;

INSERT INTO permisos (nombre, descripcion) VALUES
  ('libros.crear',    'Cargar libros y subir sus tapas'),
  ('reservas.ver',    'Ver el listado de pedidos (TP5)'),
  ('usuarios.ver',    'Ver la lista y los datos de los usuarios'),
  ('usuarios.editar', 'Editar usuarios: nombre, rol y baja lógica')
ON CONFLICT (nombre) DO NOTHING;

INSERT INTO rol_permisos (rol_id, permiso_id)
SELECT r.id, p.id FROM (VALUES
  ('personal', 'reservas.ver'),
  ('admin',    'libros.crear'),
  ('admin',    'usuarios.ver'),
  ('admin',    'usuarios.editar')
) AS rp(rol, permiso)
JOIN roles r ON r.nombre = rp.rol
JOIN permisos p ON p.nombre = rp.permiso
ON CONFLICT DO NOTHING;

-- activo = false es la baja lógica (la hace el admin): no puede ingresar y su sesión deja de valer.
CREATE TABLE IF NOT EXISTS users (
  id            INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  nombre        VARCHAR(80)  NOT NULL,
  email         VARCHAR(254) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  rol_id        INTEGER      NOT NULL REFERENCES roles(id),
  activo        BOOLEAN      NOT NULL DEFAULT true,
  created_at    TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- Bases de antes del 02/11: users.rol (texto) pasa a users.rol_id y se agrega activo. En una base nueva no hace nada.
ALTER TABLE users ADD COLUMN IF NOT EXISTS rol_id INTEGER REFERENCES roles(id);
ALTER TABLE users ADD COLUMN IF NOT EXISTS activo BOOLEAN NOT NULL DEFAULT true;
DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'users' AND column_name = 'rol') THEN
    UPDATE users u SET rol_id = r.id FROM roles r WHERE r.nombre = u.rol AND u.rol_id IS NULL;
    ALTER TABLE users DROP COLUMN rol;
  END IF;
END $$;
UPDATE users SET rol_id = (SELECT id FROM roles WHERE nombre = 'usuario') WHERE rol_id IS NULL;
ALTER TABLE users ALTER COLUMN rol_id SET NOT NULL;
CREATE INDEX IF NOT EXISTS idx_users_rol ON users (rol_id);

CREATE TABLE IF NOT EXISTS libros (
  id          INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  slug        VARCHAR(160) NOT NULL UNIQUE,   -- el id público del catálogo (/catalogo/rayuela)
  titulo      VARCHAR(150) NOT NULL,
  autor       VARCHAR(100) NOT NULL,
  genero      VARCHAR(10)  NOT NULL CHECK (genero IN ('novela', 'cuento', 'poesia', 'infantil')),
  precio      INTEGER      NOT NULL CHECK (precio > 0),
  resumen     VARCHAR(300),
  descripcion TEXT,
  autor_bio   TEXT,
  imagen_url  VARCHAR(255),                   -- vacío = portada por defecto (img/placeholder.svg)
  created_at  TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- Tapas subidas desde el alta de libros (arrastrar y soltar). Se guardan en la base y no en disco
-- porque en Render el disco se borra en cada despliegue. libros.imagen_url apunta a /api/portadas/{id}.
CREATE TABLE IF NOT EXISTS portadas (
  id         INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  tipo       VARCHAR(20) NOT NULL CHECK (tipo IN ('image/jpeg', 'image/png', 'image/webp')),
  datos      BYTEA       NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Sesiones de PHP ($_SESSION). Se guardan en la base y no en archivos porque en Render el disco se borra
-- en cada despliegue (ver Core/SesionDb). id = el valor de la cookie pawprints_sesion; datos = $_SESSION serializado.
CREATE TABLE IF NOT EXISTS sesiones (
  id    VARCHAR(256) PRIMARY KEY,
  datos TEXT         NOT NULL,
  vence TIMESTAMPTZ  NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_sesiones_vence ON sesiones (vence);
