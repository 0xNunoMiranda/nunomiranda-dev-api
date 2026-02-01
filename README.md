# nunomiranda-dev-api

## Consola Administrativa

- URL: `/admin/ui`
- Introduz o `ADMIN_SECRET` no topo da página para desbloquear as ações.
- Permite criar/suspender tenants, gerar chaves API e acompanhar o consumo de aditivos (rate limit) e pedidos.

## Stack rápida

- Node.js + Express + mysql2
- Knex para migrações e stored procedures
- UI estática em HTML/CSS/JS servida diretamente pelo Express

## Sites dos clientes

- Pasta `ruben-barbearia/` contém um site PHP reutilizável para o tenant `ruben-barbeiro`.
- Inclui landing page, widget do bot AI e painel privado `/admin-ruben-barbeiro` para configurar os aditivos (bot e WhatsApp).
- Para correr localmente: `cd ruben-barbearia && php -S localhost:8080 -t public`.
