const fs = require('fs');
const path = require('path');

const schemaPath = path.resolve(__dirname, '../sql/bootstrap.sql');

exports.up = async function up(knex) {
  const sql = fs.readFileSync(schemaPath, 'utf8');
  await knex.raw(sql);
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
