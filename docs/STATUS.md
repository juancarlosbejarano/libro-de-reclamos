# Estado del proyecto — Libro de Reclamaciones

Fecha: 2026-01-01

## Qué está listo

- PHP “vanilla” (sin Composer), routing propio (`httpdocs/` + router), sesiones y CSRF.
- Multi-tenant por dominio exacto (`tenant_domains`) y por subdominio `{slug}.<PLATFORM_BASE_DOMAIN>`.
- Registro de tenants (`/register`) y panel para agregar dominio (`/settings/domain`) con verificación DNS (A/CNAME) antes de aceptar.
- Automatización Plesk:
  - Cola en BD `domain_provisioning_jobs`
  - Cron/CLI: `scripts/plesk_provision.php` procesa jobs.
- SMTP por tenant:
  - Config por tenant con fallback a `.env`
  - Password cifrado AES-256-GCM usando `APP_KEY`.
- Respuestas a reclamos:
  - Tabla `complaint_responses`
  - Tracking de envío por canal (email/WhatsApp): `*_sent_at` + `*_error` y visualización ✓/✗.
- WhatsApp (opcional) vía Chatwoot:
  - Settings por tenant `/settings/whatsapp`
  - Envío de mensajes por Chatwoot y persistencia de `chatwoot_conversation_id`.
- RBAC por tenant:
  - roles `admin`, `staff`, `user`, `bot`
  - `user` solo ve “Mis reclamos”.
- Administración de usuarios por tenant:
  - `/settings/users` (admin-only)
  - CRUD básico + reset password
  - Tokens API para bots: generar (one-time display), listar y revocar (uno o todos).
- Panel Platform (dueño/soporte):
  - `/platform/login`, `/platform`, `/platform/tenants`, `/platform/jobs`, `/platform/reports`
  - Usuarios globales en `platform_users`
  - Heartbeat cron guardado en `system_kv` por `scripts/plesk_provision.php`.

## Instalación en hosting (Plesk)

- Instalador web: `httpdocs/install.php`
  - Crea `.env`
  - Ejecuta `database/schema.sql`
  - Crea tenant `platform` + dominio
  - Crea primer usuario `platform_users` (owner)
  - (Opcional) crea admin del tenant `platform`
  - Bloquea re-ejecución con `storage/install.lock`
  - Luego **eliminar `httpdocs/install.php`** por seguridad.

## Variables `.env` importantes

- `APP_KEY` (base64:32 bytes)
- `DB_*` (host/port/database/username/password)
- `PLATFORM_BASE_DOMAIN`
- `ALLOW_SUBDOMAIN_TENANTS`
- `DOMAIN_VERIFY_REQUIRED`
- `PLESK_*` (si usas autoprovisión)

## Próximos pasos sugeridos

- Definir cron en Plesk para ejecutar `scripts/plesk_provision.php`.
- Revisar permisos en `storage/` y `storage/uploads/`.
- (Opcional) ampliar reportes platform (entregabilidad email/whatsapp, top tenants, etc.).
