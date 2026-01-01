# Deploy en Plesk (producción) — Libro de Reclamaciones

Esta guía asume que vas a subir el proyecto comprimido y descomprimirlo en el hosting, y luego ejecutar el instalador web.

## 0) Requisitos

- Plesk con PHP `8.5` disponible para el dominio.
- MariaDB/MySQL accesible desde el sitio (normalmente `localhost`).
- Extensiones PHP recomendadas/habituales:
  - `pdo` + `pdo_mysql` (obligatorio)
  - `openssl` (obligatorio, para cifrado `APP_KEY`)
  - `mbstring` (obligatorio)
  - `curl` (recomendado: Plesk API/Chatwoot)
- Permisos de escritura en `storage/` y `storage/uploads/`.

## 1) Subir el proyecto

1. En Plesk → tu Dominio → **File Manager**.
2. Sube el ZIP del proyecto.
3. Descomprímelo en la carpeta del dominio.

### Webroot

Tu dominio debe servir desde la carpeta `httpdocs/` (es donde vive el front-controller `index.php`).

- Opción A (común): descomprimir dentro de `httpdocs/`.
- Opción B: descomprimir en otro directorio y configurar el DocumentRoot para apuntar a `httpdocs/`.

Verifica que exista:
- `httpdocs/index.php`
- `httpdocs/.htaccess`

## 2) Crear base de datos

En Plesk:
1. **Databases** → **Add Database**
2. Crea una BD (ejemplo): `libro_reclamos`
3. Crea un usuario y contraseña con permisos sobre esa BD.

Anota:
- `DB_HOST` (normalmente `127.0.0.1` o `localhost`)
- `DB_PORT` (normalmente `3306`)
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

## 3) Permisos de carpetas

Asegúrate de que el usuario con el que corre PHP (Plesk) pueda escribir:

- `storage/`
- `storage/uploads/`

Y que el proyecto pueda crear/modificar:
- `.env` en la raíz del proyecto
- `storage/install.lock`

Si Plesk usa “PHP-FPM” o “FastCGI”, estos permisos suelen ser necesarios para el instalador.

## 4) Ejecutar el instalador web

Abre:
- `https://TU-DOMINIO/install.php`

Ejemplo:
- `https://ldr.arca.digital/install.php`

El instalador:
- genera `.env`
- ejecuta `database/schema.sql`
- crea el tenant `platform` y asocia `PLATFORM_BASE_DOMAIN`
- crea el primer usuario global (tabla `platform_users`) para entrar al panel `/platform`
- opcionalmente crea un `admin` del tenant `platform`
- crea `storage/install.lock` para bloquear re-ejecución

### Datos que debes completar

- BD: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- Dominio base: `PLATFORM_BASE_DOMAIN`
  - Debe ser el dominio “apex” de la plataforma (ej: `ldr.arca.digital`)
- Flags:
  - `SESSION_SECURE`: habilítalo si usas HTTPS
  - `ALLOW_SUBDOMAIN_TENANTS`: para resolver `slug.PLATFORM_BASE_DOMAIN`
  - `DOMAIN_VERIFY_REQUIRED`: obliga verificación DNS al agregar dominios
  - `PLESK_AUTO_PROVISION`: si quieres que cree alias en Plesk (requiere credenciales de API)

## 5) Post-instalación (seguridad)

1. **Elimina** el instalador del servidor:
   - `httpdocs/install.php`
2. Verifica que NO exista un `.env` dentro de `httpdocs/`.
   - `.env` debe estar en la raíz del proyecto (un nivel arriba), donde lo lee `app/bootstrap.php`.
3. Confirma que `storage/install.lock` existe.

## 6) URLs importantes

- App (tenant plataforma): `/`
- Login tenant: `/login`
- Registro de tenants (si está habilitado): `/register`
- Panel platform (dueño/soporte):
  - Login: `/platform/login`
  - Dashboard: `/platform`
  - Tenants: `/platform/tenants`
  - Jobs Plesk: `/platform/jobs`
  - Reportes: `/platform/reports`

## 7) SSL / HTTPS

- En Plesk, habilita **SSL/TLS Certificates** (Let’s Encrypt).
- Con HTTPS activo:
  - `SESSION_SECURE=1`

## 8) Subdominios y wildcard (multi-tenant por subdominio)

Si vas a usar tenants por subdominio (ej: `cliente1.ldr.arca.digital`):

- Asegura que `ALLOW_SUBDOMAIN_TENANTS=1`
- Crea el DNS wildcard:
  - `*.ldr.arca.digital` apuntando al mismo hosting (A/CNAME según tu DNS)

Sin wildcard, solo funcionarán los dominios que existan específicamente en DNS.

## 9) Verificación de dominios (dominios custom)

El sistema puede requerir verificación DNS al agregar un dominio en `/settings/domain`.

Variables relevantes:
- `DOMAIN_VERIFY_REQUIRED=1` (recomendado)
- `PLATFORM_ALLOWED_IPS`:
  - lista de IPs permitidas para validar A record del dominio del tenant
  - formato: `"1.2.3.4, 5.6.7.8"`

## 10) Plesk Auto-Provision (alias de dominio)

Si quieres que el sistema cree automáticamente alias en Plesk cuando un tenant verifica su dominio:

1. Activa:
   - `PLESK_AUTO_PROVISION=1`
2. Completa en `.env`:
   - `PLESK_API_URL` (ej: `https://127.0.0.1:8443/enterprise/control/agent.php`)
   - `PLESK_API_KEY` (secret)
   - `PLESK_VERIFY_TLS=1` (recomendado)

### Cron (requerido para jobs)

El alta de alias se encola en `domain_provisioning_jobs` y se procesa por cron.

En Plesk → **Scheduled Tasks** (o Cron Jobs) configura, por ejemplo, cada 2–5 minutos:

- Comando:
  - `php /ABSOLUTE/PATH/scripts/plesk_provision.php`

Notas:
- Usa la ruta absoluta que corresponda a tu hosting.
- Asegura que el cron use PHP 8.5 (o el binario correcto).

## 11) Email (SMTP)

El sistema soporta configuración SMTP por tenant.

- En cada tenant: `/settings/mail`
- Si no hay config por tenant, usa fallback `.env` (`MAIL_*` si los defines).

Recomendaciones:
- Verifica que tu hosting permita conexiones salientes SMTP.
- Si usas Office365/Gmail, considera restricciones de autenticación y puertos.

## 12) WhatsApp (Chatwoot) opcional

Cada tenant puede habilitar envío por WhatsApp desde:
- `/settings/whatsapp`

Esto requiere:
- `chatwoot_base_url`
- `account_id`, `inbox_id`, `api_access_token`

## 13) Checklist rápido de verificación

- `/health` responde `{ ok: true }`
- `/` carga la home sin 500
- Login tenant funciona
- `/platform/login` funciona
- Crear un reclamo y responderlo
- Si cron está habilitado: `/platform/jobs` refleja cambios de estado

## 14) Troubleshooting

### Error 500 al instalar
- Revisa extensiones (pdo_mysql/openssl/mbstring)
- Revisa permisos de escritura (`storage/`, `.env`)
- Revisa credenciales y permisos de BD (crear tablas)

### No resuelve subdominios
- Falta wildcard DNS `*.PLATFORM_BASE_DOMAIN`
- `ALLOW_SUBDOMAIN_TENANTS=0`

### Cron no procesa jobs
- Cron no se está ejecutando
- Ruta al PHP incorrecta
- Permisos del usuario de cron
- Credenciales `PLESK_*` incorrectas

## 15) Backups

Como mínimo respalda:
- Base de datos
- `.env` (contiene secretos)
- `storage/uploads/` (si usas adjuntos)
