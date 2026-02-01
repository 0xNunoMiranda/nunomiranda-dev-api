const applySchema = require('./helpers/applySchema');

exports.up = async function up(knex) {
  await applySchema(knex, 'bootstrap.sql');
};

exports.down = async function down(knex) {
  await knex.schema
    .dropTableIfExists('tenant_api_keys')
    .dropTableIfExists('tenants');

  await knex.raw('DROP PROCEDURE IF EXISTS sp_lookup_api_key');
  await knex.raw('DROP PROCEDURE IF EXISTS sp_revoke_api_key');
  await knex.raw('DROP PROCEDURE IF EXISTS sp_create_tenant_api_key');
  await knex.raw('DROP PROCEDURE IF EXISTS sp_create_tenant');
};
