const applySchema = require('./helpers/applySchema');

exports.up = async function up(knex) {
  await applySchema(knex, 'bootstrap.sql');
};

exports.down = async function down(knex) {
  await knex.raw('DROP PROCEDURE IF EXISTS sp_dashboard_by_date');
  await knex.raw('DROP PROCEDURE IF EXISTS sp_dashboard_pending');
  await knex.raw('DROP PROCEDURE IF EXISTS sp_dashboard_summary');
};
