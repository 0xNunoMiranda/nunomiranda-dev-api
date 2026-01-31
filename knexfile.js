require('dotenv').config();

/** @type {import('knex').Knex.Config} */
module.exports = {
  client: 'mysql2',
  connection: {
    host: process.env.DB_HOST,
    port: Number(process.env.DB_PORT || 3306),
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_NAME,
    multipleStatements: true,
  },
  migrations: {
    directory: './migrations',
  },
};
