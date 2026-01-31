import express from 'express';
import pinoHttp from 'pino-http';
import config from './config';
import logger from './logger';
import errorHandler from './middleware/errorHandler';
import { success } from './responses';
import adminRoutes from './routes/admin';
import requestRoutes from './routes/requests';
import tenantRoutes from './routes/tenant';

const app = express();

app.use(express.json());
app.use(express.urlencoded({ extended: false }));
app.use(
  pinoHttp({
    logger,
    customLogLevel: (res, err) => {
      const statusCode = res.statusCode ?? 200;
      if (statusCode >= 500 || err) return 'error';
      if (statusCode >= 400) return 'warn';
      return 'info';
    },
  }),
);

app.get('/health', (_req, res) => {
  res.json(success());
});

app.get('/version', (_req, res) => {
  res.json(
    success({
      version: config.APP_VERSION || process.env.npm_package_version || 'dev',
    }),
  );
});

app.use('/admin', adminRoutes);
app.use('/requests', requestRoutes);
app.use('/', tenantRoutes);

app.use(errorHandler);

app.listen(config.APP_PORT, () => {
  logger.info({ port: config.APP_PORT }, 'API listening');
});
