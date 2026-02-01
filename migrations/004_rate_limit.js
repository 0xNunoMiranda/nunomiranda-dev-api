const applySchema = require('./helpers/applySchema');

exports.up = async function up(knex) {
  await applySchema(knex, 'bootstrap.sql');
};

exports.down = async function down(knex) {
  await knex.schema.dropTableIfExists('tenant_rate_limit_windows');
  await knex.raw('DROP PROCEDURE IF EXISTS sp_check_rate_limit');
};
