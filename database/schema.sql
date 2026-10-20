-- PAWPrints · esquema de la base (PostgreSQL / Neon). Se ejecuta una vez en el SQL Editor de Neon
-- (o con psql). Es idempotente: se puede volver a correr sin romper datos.

CREATE TABLE IF NOT EXISTS users (
  id            INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  nombre        VARCHAR(80)  NOT NULL,
  email         VARCHAR(254) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  rol           VARCHAR(10)  NOT NULL DEFAULT 'usuario' CHECK (rol IN ('usuario', 'admin')),
  created_at    TIMESTAMPTZ  NOT NULL DEFAULT now()
);

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
