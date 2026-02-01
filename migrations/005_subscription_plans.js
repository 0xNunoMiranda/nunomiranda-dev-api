const applySchema = require('./helpers/applySchema');

exports.up = async function up(knex) {
  await applySchema(knex, 'bootstrap.sql');
};

exports.down = async function down(knex) {
  await knex.schema.dropTableIfExists('subscription_plans');
};
