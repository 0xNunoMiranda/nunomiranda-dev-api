import { NextFunction, Request, Response } from 'express';
import { ApiError, createForbidden, createUnauthorized } from '../errors';
import { failure } from '../responses';
import { findTenantKeyByPublicId } from '../services/tenantService';
import { hashApiKey } from '../utils/apiKey';

const parseAuthorizationHeader = (authHeader: string | undefined) => {
  if (!authHeader) return null;
  const [type, value] = authHeader.split(' ');
  if (type?.toLowerCase() !== 'bearer' || !value) return null;
  const [publicId, secret] = value.split('.');
  if (!publicId || !secret) return null;
  return { publicId, secret };
};

const ensureScopes = (keyScopes: string[], required: string[]) =>
  required.every((scope) => keyScopes.includes(scope));

export const requireTenantAuth = (requiredScopes: string[] = []) =>
  async (req: Request, res: Response, next: NextFunction) => {
    try {
      const parsed = parseAuthorizationHeader(req.header('authorization'));
      if (!parsed) {
        throw createUnauthorized('Missing or invalid API key');
      }

      const keyRow = await findTenantKeyByPublicId(parsed.publicId);
      if (!keyRow || keyRow.revoked_at) {
        throw createUnauthorized('API key revoked or invalid');
      }
      if (keyRow.tenant_status !== 'active') {
        throw createForbidden('Tenant inactive');
      }

      const expectedHash = hashApiKey(parsed.secret, keyRow.salt);
      if (expectedHash !== keyRow.key_hash) {
        throw createUnauthorized('API key mismatch');
      }

      const scopes = JSON.parse(keyRow.scopes_json) as string[];
      if (!ensureScopes(scopes, requiredScopes)) {
        throw createForbidden('Missing required scope');
      }

      req.tenant = {
        tenantId: keyRow.tenant_id,
        scopes,
        apiKeyId: keyRow.id,
      };

      next();
    } catch (error) {
      if (error instanceof ApiError) {
        return res.status(error.statusCode).json(failure(error.code, error.message));
      }
      return res.status(401).json(failure('unauthorized', 'Invalid API key'));
    }
  };
