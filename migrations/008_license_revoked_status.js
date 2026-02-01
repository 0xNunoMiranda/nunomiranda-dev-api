/**
 * Migration: Add 'revoked' status to client_licenses
 */

exports.up = async function(knex) {
    // Add 'revoked' to the status enum
    await knex.raw(`
        ALTER TABLE client_licenses 
        MODIFY COLUMN status ENUM('active', 'suspended', 'expired', 'trial', 'revoked') 
        DEFAULT 'trial'
    `);
};

exports.down = async function(knex) {
    // Remove 'revoked' from the status enum (data may be lost)
    await knex.raw(`
        UPDATE client_licenses SET status = 'expired' WHERE status = 'revoked'
    `);
    await knex.raw(`
        ALTER TABLE client_licenses 
        MODIFY COLUMN status ENUM('active', 'suspended', 'expired', 'trial') 
        DEFAULT 'trial'
    `);
};
