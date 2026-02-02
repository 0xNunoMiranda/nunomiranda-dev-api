/**
 * Migration: Fix client_licenses.license_key length
 *
 * License keys are in the format: ntk_[12 hex].[48 hex] (65 chars total).
 * Previous schema used VARCHAR(64), which truncates the last character and breaks validation in setup.php.
 */

exports.up = async function up(knex) {
  await knex.raw(`
    ALTER TABLE client_licenses
    MODIFY license_key VARCHAR(65) NOT NULL
  `);
};

exports.down = async function down(knex) {
  await knex.raw(`
    ALTER TABLE client_licenses
    MODIFY license_key VARCHAR(64) NOT NULL
  `);
};

