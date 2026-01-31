import mysql from 'mysql2/promise';
import config from './config';
import logger from './logger';

const pool = mysql.createPool({
  host: config.DB_HOST,
  port: config.DB_PORT,
  user: config.DB_USER,
  password: config.DB_PASSWORD,
  database: config.DB_NAME,
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0,
  multipleStatements: true,
});

pool
  .getConnection()
  .then((conn) => {
    conn.release();
    logger.debug('MySQL connection pool ready');
  })
  .catch((error) => {
    logger.error({ error }, 'Unable to connect to MySQL');
    process.exit(1);
  });

export default pool;
