import crypto from 'crypto';
import config from '../config';

export type GeneratedApiKey = {
  apiKey: string;
  publicId: string;
  secret: string;
  salt: string;
  hash: string;
};

const randomHex = (bytes: number) => crypto.randomBytes(bytes).toString('hex');

export const generateApiKey = (): GeneratedApiKey => {
  const publicId = `ntk_${randomHex(6)}`;
  const secret = randomHex(24);
  const salt = randomHex(8);
  const apiKey = `${publicId}.${secret}`;
  const hash = hashApiKey(secret, salt);
  return { apiKey, publicId, secret, salt, hash };
};

export const hashApiKey = (secret: string, salt: string) =>
  crypto.createHash('sha256').update(`${salt}:${secret}:${config.API_KEY_GLOBAL_SALT}`).digest('hex');
