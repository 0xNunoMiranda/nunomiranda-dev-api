import { NextFunction, Request, Response } from 'express';
import { ApiError, createForbidden, createUnauthorized } from '../errors';
import { failure } from '../responses';
import { findTenantById, findTenantKeyByPublicId } from '../services/tenantService';
import { LicenseService } from '../services/licenseService';
import { hashApiKey } from '../utils/apiKey';

const parseBearerToken = (authHeader: string | undefined) => {
  if (!authHeader) return null;
  const [type, value] = authHeader.split(' ');
  if (type?.toLowerCase() !== 'bearer' || !value) return null;
  return value.trim();
};

const parseApiKeyToken = (token: string) => {
  const [publicId, secret] = token.split('.');
  if (!publicId || !secret) return null;
  return { publicId, secret };
};

const ensureScopes = (keyScopes: string[], required: string[]) =>
  required.every((scope) => keyScopes.includes(scope));

export const requireTenantAuth = (requiredScopes: string[] = []) =>
  async (req: Request, res: Response, next: NextFunction) => {
    try {
      const token = parseBearerToken(req.header('authorization'));
      if (!token) {
        throw createUnauthorized('Missing or invalid API key');
      }

      // If the bearer token is a SiteForge license key, skip tenant_api_keys lookup entirely.
      // This avoids issues with DB/procedure collation mismatches and enforces "single customer key" behavior.
      if (token.startsWith('ntk_')) {
        const license = await LicenseService.getLicenseByKey(token);
        if (!license) {
          throw createUnauthorized('API key revoked or invalid');
        }

        if (license.status === 'suspended') {
          throw createForbidden('License suspended');
        }
        if (license.status === 'expired') {
          throw createForbidden('License expired');
        }
        if (license.status === 'revoked') {
          throw createForbidden('License revoked');
        }

        const tenant = await findTenantById(license.tenant_id);
        if (!tenant || tenant.status !== 'active') {
          throw createForbidden('Tenant inactive');
        }

        const scopes = ['requests:read', 'requests:write'];
        if (!ensureScopes(scopes, requiredScopes)) {
          throw createForbidden('Missing required scope');
        }

        req.tenant = {
          tenantId: license.tenant_id,
          scopes,
          apiKeyId: null,
        };

        return next();
      }

      const parsed = parseApiKeyToken(token);
      if (!parsed) {
        throw createUnauthorized('Missing or invalid API key');
      }

      const keyRow = await findTenantKeyByPublicId(parsed.publicId);

      if (keyRow && !keyRow.revoked_at) {
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

        return next();
      }

      // Fallback: allow using the SiteForge license key as the bearer token.
      // This makes the setup.php license usable as the single "customer key" for client integrations.
      const fullKey = `${parsed.publicId}.${parsed.secret}`;
      const license = await LicenseService.getLicenseByKey(fullKey);
      if (!license) {
        throw createUnauthorized('API key revoked or invalid');
      }

      if (license.status === 'suspended') {
        throw createForbidden('License suspended');
      }
      if (license.status === 'expired') {
        throw createForbidden('License expired');
      }
      if (license.status === 'revoked') {
        throw createForbidden('License revoked');
      }

      const tenant = await findTenantById(license.tenant_id);
      if (!tenant || tenant.status !== 'active') {
        throw createForbidden('Tenant inactive');
      }

      const scopes = ['requests:read', 'requests:write'];
      if (!ensureScopes(scopes, requiredScopes)) {
        throw createForbidden('Missing required scope');
      }

      req.tenant = {
        tenantId: license.tenant_id,
        scopes,
        apiKeyId: null,
      };

      return next();
    } catch (error) {
      if (error instanceof ApiError) {
        return res.status(error.statusCode).json(failure(error.code, error.message));
      }
      return res.status(401).json(failure('unauthorized', 'Invalid API key'));
    }
  };
