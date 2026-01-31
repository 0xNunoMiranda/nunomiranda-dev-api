import { Router } from 'express';
import { z } from 'zod';
import { createBadRequest } from '../errors';
import requireAdminSecret from '../middleware/adminSecret';
import { success } from '../responses';
import { createTenant, createTenantApiKey, revokeTenantApiKey } from '../services/tenantService';
import { generateApiKey } from '../utils/apiKey';

const router = Router();

router.use(requireAdminSecret);

const tenantSchema = z.object({
  name: z.string().min(3),
  slug: z.string().regex(/^[a-z0-9-]+$/),
  defaultContext: z.string().optional(),
  metadata: z.record(z.any()).optional(),
});

router.post('/tenants', async (req, res, next) => {
  try {
    const body = tenantSchema.parse(req.body);
    const tenant = await createTenant({
      name: body.name,
      slug: body.slug,
      defaultContext: body.defaultContext ?? null,
      metadata: body.metadata ?? null,
    });
    return res.json(success({ tenant }));
  } catch (error) {
    if (error instanceof z.ZodError) {
      return next(createBadRequest(error.errors[0]?.message || 'Invalid payload'));
    }
    return next(error);
  }
});

const keySchema = z.object({
  label: z.string().optional(),
  scopes: z.array(z.string()).default(['requests:read', 'requests:write']),
});

router.post('/tenants/:tenantId/keys', async (req, res, next) => {
  try {
    const tenantId = Number(req.params.tenantId);
    if (Number.isNaN(tenantId)) {
      throw createBadRequest('Invalid tenantId');
    }
    const body = keySchema.parse(req.body ?? {});
    const generated = generateApiKey();
    const keyRow = await createTenantApiKey({
      tenantId,
      publicId: generated.publicId,
      label: body.label ?? null,
      keyHash: generated.hash,
      salt: generated.salt,
      scopes: body.scopes,
    });

    return res.json(
      success({
        apiKey: generated.apiKey,
        keyId: keyRow.id,
        tenantId: keyRow.tenant_id,
        scopes: body.scopes,
      }),
    );
  } catch (error) {
    if (error instanceof z.ZodError) {
      return next(createBadRequest(error.errors[0]?.message || 'Invalid payload'));
    }
    return next(error);
  }
});

const revokeSchema = z.object({ keyId: z.coerce.number().int().positive() });

router.post('/keys/revoke', async (req, res, next) => {
  try {
    const body = revokeSchema.parse(req.body);
    const keyRow = await revokeTenantApiKey(body.keyId);
    if (!keyRow) {
      throw createBadRequest('Key not found');
    }
    return res.json(success({ key: keyRow }));
  } catch (error) {
    if (error instanceof z.ZodError) {
      return next(createBadRequest(error.errors[0]?.message || 'Invalid payload'));
    }
    return next(error);
  }
});

export default router;
