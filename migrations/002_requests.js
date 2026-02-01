const applySchema = require('./helpers/applySchema');

exports.up = async function up(knex) {
  await applySchema(knex, 'bootstrap.sql');
};

exports.down = async function down(knex) {
  await knex.schema.dropTableIfExists('request_events');
  await knex.schema.dropTableIfExists('requests');

  await knex.raw('DROP PROCEDURE IF EXISTS sp_update_request');
  await knex.raw('DROP PROCEDURE IF EXISTS sp_list_requests');
  await knex.raw('DROP PROCEDURE IF EXISTS sp_list_request_events');
  await knex.raw('DROP PROCEDURE IF EXISTS sp_get_request_by_id');
  await knex.raw('DROP PROCEDURE IF EXISTS sp_create_request');
  await knex.raw('DROP PROCEDURE IF EXISTS sp_insert_request_event');
};
