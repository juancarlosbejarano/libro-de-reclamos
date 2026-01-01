-- Minimal seed data

INSERT INTO tenants (slug, name, created_at)
VALUES ('platform', 'Plataforma Libro de Reclamaciones', NOW())
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Associate at least one domain to your tenant.
-- Platform domain (apex)
INSERT INTO tenant_domains (tenant_id, domain, kind, is_primary, verified_at, created_at)
SELECT id, 'ldr.arca.digital', 'platform', 1, NOW(), NOW() FROM tenants WHERE slug='platform'
ON DUPLICATE KEY UPDATE kind='platform', is_primary=1;

-- Nota: para crear el admin y el primer tenant cliente, usa scripts/seed.php

