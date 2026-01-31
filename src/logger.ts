import pino from 'pino';
import config from './config';

const logger = pino({
  level: config.NODE_ENV === 'production' ? 'info' : 'debug',
  base: { env: config.NODE_ENV },
});

export default logger;
