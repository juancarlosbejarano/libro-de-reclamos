# Libro de Reclamaciones (PHP + MariaDB para Plesk)

Sistema “vanilla PHP” (sin Composer) listo para subir a **hosting Plesk**, con:

- **Multiempresa / multitenancy por dominio** (resuelve tenant por `Host`)
- **Multiusuario** (roles: `admin`, `staff`, `user`)
- **API** (`/api/v1`) con tokens Bearer
- **I18n** (es/en) simple
- **PWA mínima** (manifest + service worker)
- **MariaDB** como base de datos (PDO)

## Requisitos

- PHP 8.1+ (recomendado 8.2/8.3) con extensiones `pdo_mysql`.
- MariaDB 10.4+ (en Plesk suele venir).

## Despliegue en Plesk (pasos)

1) Sube el contenido del repo al hosting (por Git o File Manager).

2) En Plesk crea la base de datos y usuario:

- Database: por ejemplo `libro_reclamos`
- User: por ejemplo `libro_user`

3) Importa el esquema:

- Abre **phpMyAdmin** (o “Importar” desde Plesk) e importa:
  - `database/schema.sql`
  - (opcional) `database/seed.sql`

4) Configura el entorno:

- Copia `.env.example` a `.env` y completa credenciales.

5) Document Root:

- Asegura que el document root del dominio apunte a `httpdocs`.
  - En Plesk suele ser `httpdocs/` por defecto.

6) Rewrite (routing):

- Si tu hosting usa Apache, `httpdocs/.htaccess` ya enruta a `index.php`.
- Si usa Nginx como proxy, habilita “Pretty URLs”/rewrite en Plesk o usa regla equivalente.

7) Verificación:

- `https://TU-DOMINIO/health`

Credenciales seed (si importaste `seed.sql`):

- Email: `admin@example.com`
- Password: `admin12345`

## Multi-tenant por dominio

El sistema soporta dos formas:

1) **Dominio propio** (custom): el `Host` debe existir en `tenant_domains.domain`.
2) **Subdominio de plataforma**: `SLUG.ldr.arca.digital` resuelve el tenant por `tenants.slug`.

Por defecto, si el `Host` no coincide con un dominio registrado, usa `DEFAULT_TENANT_SLUG` (recomendado `platform`).

### Registro sin dominio propio

El cliente puede registrarse en `/register` y obtener un subdominio `slug.ldr.arca.digital`. Luego puede ir a **Configuración → Dominio** para agregar su dominio propio y apuntarlo al hosting.

## Checklist: dominio propio del cliente (DNS + Plesk)

Este checklist aplica cuando el cliente quiere usar `tudominio.com` en lugar de `slug.ldr.arca.digital`.

1) **Elegir método DNS**

- Recomendado: **CNAME**
  - `www.tudominio.com` → CNAME a `slug.ldr.arca.digital`
  - (Opcional) redirigir `tudominio.com` → `www.tudominio.com`
- Alternativo: **A record**
  - `tudominio.com` → A a la IP del hosting
  - `www.tudominio.com` → A a la misma IP o CNAME a `tudominio.com`

2) **Esperar propagación DNS**

- Si acabas de cambiar DNS, puede tardar (TTL). Hasta que resuelva correctamente, el sistema no permitirá registrar el dominio.

3) **Plesk: agregar dominio/alias**

- En Plesk, agrega el dominio como **Domain Alias** (o dominio adicional) apuntando al mismo sitio.
- Asegura que el Document Root siga siendo `httpdocs`.

4) **SSL**

- Emite certificado (Let’s Encrypt) para `tudominio.com` y/o `www.tudominio.com`.

5) **Registrar el dominio en el panel**

- Entra como admin del tenant → **Configuración → Dominio** y agrega `tudominio.com`.
- El sistema valida que el DNS apunte al hosting (A) o sea CNAME al subdominio del tenant.

### Notas de validación DNS (cómo decide el sistema)

- Acepta **CNAME** si apunta a:
  - `slug.ldr.arca.digital` (preferido)
  - `ldr.arca.digital`
- Acepta **A** si la IP coincide con:
  - `PLATFORM_ALLOWED_IPS` (recomendado; por ejemplo `207.58.173.84`), o
  - alguna IP A de `ldr.arca.digital` (fallback)

> Limitación: esta validación confirma “apunta”, pero no prueba control total del dominio (lo ideal es TXT). Si quieres, puedo agregar verificación por TXT como mejora.

## Automatización Plesk (crear alias automáticamente)

Cuando un dominio custom pasa la verificación DNS, el sistema lo **encola** para aprovisionarlo en Plesk como **Domain Alias** del sitio principal.

### Configuración

En `.env` completa:

- `PLESK_API_URL` (por ejemplo `https://cloud.portalsee.com:8443/enterprise/control/agent.php`)
- `PLESK_API_KEY` (API key de Plesk)
- `PLESK_SITE_NAME=ldr.arca.digital`
- `PLESK_AUTO_PROVISION=true`

Puedes validar conectividad desde el hosting con:

```bash
php scripts/plesk_ping.php
```

### Ejecutar el aprovisionamiento

Configura un cron en Plesk (Scheduled Tasks) para ejecutar:

```bash
php scripts/plesk_provision.php
```

Este script procesa jobs `pending` de la tabla `domain_provisioning_jobs` y llama la API XML de Plesk para crear el alias.

## SMTP por cliente (multi-tenant)

Cada cliente (tenant) puede configurar su propio SMTP en:

- `/settings/mail` (solo admin)

Si el cliente no configura SMTP, el sistema usa los valores por defecto del servidor desde `.env`:

- `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_EMAIL`, `MAIL_FROM_NAME`

Notas:

- La contraseña del SMTP del cliente se guarda cifrada en BD; para esto debes definir `APP_KEY` (32 bytes base64) en el servidor.
- Al crear un reclamo, el sistema intenta notificar por email a los admins del tenant (best-effort; si falla no bloquea el registro).

### Scripts seguros (CLI)

- Generar `APP_KEY`:

```bash
php scripts/generate_app_key.php
```

- Probar envío SMTP (con protecciones anti-spam):
  - Solo corre en CLI
  - Requiere `MAIL_TEST_ENABLED=true`
  - Requiere `--confirm=true`
  - Solo envía a emails `admin` del tenant

```bash
php scripts/mail_test.php --tenant-slug=miempresa --confirm=true
```

## Enviar por WhatsApp (Chatwoot) (opcional)

Además del correo, el sistema puede notificar las **respuestas** de un reclamo por WhatsApp usando Chatwoot, manteniendo el **histórico** en una conversación.

- Configuración (solo admin): `/settings/whatsapp`
- El token de Chatwoot se guarda cifrado (requiere `APP_KEY` en el servidor).

### Datos necesarios en Chatwoot

- `URL de Chatwoot`: por defecto `https://portalchat.arca.digital/`
- `Account ID`
- `Inbox ID (WhatsApp)`
- `API Access Token`

### Flujo

- Cuando admin/staff agrega una **respuesta** dentro del reclamo, el sistema intenta:
  - Enviar email al cliente si existe `customer_email`.
  - Enviar mensaje a Chatwoot/WhatsApp si el módulo está habilitado y existe `customer_phone`.
- La primera vez se crea contacto + conversación en Chatwoot, y se guarda `chatwoot_conversation_id` en el reclamo para seguir el histórico.

### Nota si ya tienes BD creada

Si ya importaste una versión anterior del esquema, aplica estos cambios (o re-importa `database/schema.sql` en una BD nueva):

```sql
ALTER TABLE users MODIFY role ENUM('admin','staff','user','bot') NOT NULL DEFAULT 'user';

ALTER TABLE complaints
  ADD COLUMN customer_name VARCHAR(180) NULL,
  ADD COLUMN customer_email VARCHAR(255) NULL,
  ADD COLUMN customer_phone VARCHAR(32) NULL,
  ADD COLUMN chatwoot_conversation_id BIGINT UNSIGNED NULL;

CREATE INDEX idx_complaints_customer_email ON complaints(customer_email);

CREATE TABLE IF NOT EXISTS complaint_responses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id INT UNSIGNED NOT NULL,
  complaint_id BIGINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  message TEXT NOT NULL,
  email_sent_at DATETIME NULL,
  whatsapp_sent_at DATETIME NULL,
  email_error VARCHAR(255) NULL,
  whatsapp_error VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_responses_complaint (complaint_id),
  KEY idx_responses_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tenant_whatsapp_settings (
  tenant_id INT UNSIGNED NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  chatwoot_base_url VARCHAR(255) NOT NULL,
  account_id INT UNSIGNED NOT NULL,
  inbox_id INT UNSIGNED NOT NULL,
  api_token_enc TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Roles y permisos (por tenant)

El sistema es multi-tenant: **cada empresa (tenant) tiene sus propios usuarios**.

- `admin` (Administrador de empresa)
  - Acceso a configuración: dominio, SMTP, WhatsApp.
  - Puede ver y responder reclamos.
- `staff` (Empleado de empresa)
  - Puede ver y responder reclamos.
  - No accede a configuración.
- `user` (Usuario externo/cliente)
  - Puede iniciar sesión y ver **sus reclamos** en `/my/complaints`.
  - Por seguridad, si está logueado como `user`, no puede abrir reclamos de otros.
- `bot` (Cuenta técnica)
  - Pensado para integraciones vía API (tokens Bearer) sin acceso UI.
  - UI para generar/revocar tokens: `/settings/users` (solo admin).

## Administración de Plataforma (dueño / soporte)

Como dueño del sistema puedes administrar a nivel global (multi-tenant) desde:

- `/platform` (dashboard + estado del sistema)
- `/platform/tenants` (lista de empresas)
- `/platform/jobs` (cola de provisión Plesk: pending/failed)
- `/platform/reports` (reclamos por día últimos 14 días)

### Consulta RUC/DNI (Arca) + creación de empresas

1) Configura el token (se guarda cifrado en BD):

- `/platform/settings/arca`

2) Crea empresas (tenants) desde:

- `/platform/tenants` → **Crear empresa**

En el formulario puedes ingresar **RUC** o **DNI** y presionar **Consultar** para autocompletar el nombre.

> Nota: requiere `APP_KEY` válido para cifrar el token.

### Nota si ya tienes BD creada (migración)

Si tu base ya existe y no quieres re-importar `database/schema.sql`, aplica:

```sql
ALTER TABLE tenants
  ADD COLUMN id_type ENUM('ruc','dni') NULL,
  ADD COLUMN id_number VARCHAR(16) NULL;
```

### Crear usuario de plataforma (CLI)

Primero crea un usuario `owner` o `support`:

```bash
php scripts/create_platform_user.php --email=owner@arca.digital --password=StrongPass123 --role=owner
```

Si estás parado dentro de `httpdocs/` (como suele pasar en SSH de Plesk), usa:

```bash
php ../scripts/create_platform_user.php --email=owner@arca.digital --password=StrongPass123 --role=owner
```

Luego ingresa en `/platform/login`.

## Instalación en Plesk (install.php)

Para instalar en hosting:

1. Crea la base de datos y usuario en Plesk.
2. Sube el proyecto y descomprímelo (el webroot del dominio debe apuntar a `httpdocs/`).
3. Abre: `/install.php`
4. Completa credenciales de BD + datos de plataforma.
5. Al finalizar, elimina `httpdocs/install.php` del servidor por seguridad.

Guía detallada de despliegue: `docs/DEPLOY_PLESK.md`

## Actualizaciones (update.php)

Cuando subas una nueva versión que requiera cambios en la base de datos, puedes usar:

- `/update.php`

Este archivo solo permite ejecución si estás logueado en `/platform/login` con un usuario `owner`.

Por seguridad, luego de ejecutar, elimina `httpdocs/update.php` del servidor.


## API (ejemplos)

- Obtener token:

```bash
curl -X POST https://TU-DOMINIO/api/v1/auth/token \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"admin12345"}'
```

- Listar reclamos:

```bash
curl https://TU-DOMINIO/api/v1/complaints \
  -H "Authorization: Bearer <TOKEN>"
```

## Estructura

- `httpdocs/` Document root (front controller + assets)
- `app/` Núcleo (router, controllers, models, auth, tenancy)
- `database/` SQL schema/seed
- `storage/` logs y uploads

