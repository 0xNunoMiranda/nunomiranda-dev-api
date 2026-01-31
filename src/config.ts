import { config as loadEnv } from 'dotenv';
import { z } from 'zod';

loadEnv();

const envSchema = z.object({
  NODE_ENV: z.enum(['development', 'test', 'production']).default('development'),
  APP_PORT: z.coerce.number().default(3000),
  DB_HOST: z.string(),
  DB_PORT: z.coerce.number().default(3306),
  DB_USER: z.string(),
  DB_PASSWORD: z.string(),
  DB_NAME: z.string(),
  ADMIN_SECRET: z.string().min(8, 'ADMIN_SECRET must be at least 8 characters'),
  API_KEY_GLOBAL_SALT: z.string().min(16, 'API_KEY_GLOBAL_SALT must be at least 16 characters'),
  HTTP_RATE_LIMIT_PER_MIN: z.coerce.number().default(60),
  APP_VERSION: z.string().optional(),
});

const env = envSchema.parse(process.env);

export type AppConfig = typeof env;

export default env;
