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
  HTTP_RATE_LIMIT_WINDOW_SECONDS: z.coerce.number().default(60),
  APP_VERSION: z.string().optional(),
  // OpenAI
  OPENAI_API_KEY: z.string().optional(),
  // Easypay credentials
  EASYPAY_ACCOUNT_ID: z.string().default('2b0f63e2-9fb5-4e52-aca0-b4bf0339bbe6'),
  EASYPAY_API_KEY: z.string().default('eae4aa59-8e5b-4ec2-887d-b02768481a92'),
  EASYPAY_API_BASE: z.string().default('https://api.test.easypay.pt/2.0'),
  EASYPAY_WEBHOOK_SECRET: z.string().optional(),
});

const env = envSchema.parse(process.env);

export type AppConfig = typeof env;

export default env;
