-- init.sql

/* for mysql
DO $$
BEGIN
   IF NOT EXISTS (SELECT FROM pg_database WHERE datname = 'laravel-vue-app') THEN
      CREATE DATABASE "laravel-vue-app";
   END IF;
END$$;

DO $$
BEGIN
   IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'laravel-vue-app_user') THEN
      CREATE USER "laravel-vue-app_user" WITH PASSWORD '123TestLaravelVueUser#321!';
   ELSE
      ALTER USER "laravel-vue-app_user" WITH PASSWORD '123TestLaravelVueUser#321!';
   END IF;
END$$;

GRANT ALL PRIVILEGES ON DATABASE "laravel-vue-app" TO "laravel-vue-app_user";
\c "laravel-vue-app";
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO "laravel-vue-app_user";
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO "laravel-vue-app_user";
GRANT USAGE ON SCHEMA public TO "laravel-vue-app_user"; */

--postgres
-- Create database if it does not exist
SELECT 'CREATE DATABASE "laravel-vue-app"'
WHERE NOT EXISTS (
    SELECT FROM pg_database
    WHERE datname = 'laravel-vue-app'
)\gexec

-- Create user if it does not exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT FROM pg_roles
        WHERE rolname = 'laravel-vue-app_user'
    ) THEN
        CREATE USER "laravel-vue-app_user"
        WITH PASSWORD '123TestLaravelVueUser#321!';
    ELSE
        ALTER USER "laravel-vue-app_user"
        WITH PASSWORD '123TestLaravelVueUser#321!';
    END IF;
END
$$;

-- Grant database privileges
GRANT ALL PRIVILEGES ON DATABASE "laravel-vue-app"
TO "laravel-vue-app_user";

-- Connect to database
\connect "laravel-vue-app"

-- Grant schema privileges
GRANT USAGE, CREATE ON SCHEMA public
TO "laravel-vue-app_user";

-- Grant privileges on existing tables
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public
TO "laravel-vue-app_user";

-- Grant privileges on existing sequences
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public
TO "laravel-vue-app_user";

-- Set default privileges for future tables
ALTER DEFAULT PRIVILEGES IN SCHEMA public
GRANT ALL PRIVILEGES ON TABLES
TO "laravel-vue-app_user";

-- Set default privileges for future sequences
ALTER DEFAULT PRIVILEGES IN SCHEMA public
GRANT ALL PRIVILEGES ON SEQUENCES
TO "laravel-vue-app_user";