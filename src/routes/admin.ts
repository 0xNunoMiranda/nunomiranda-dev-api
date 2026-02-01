import { Router } from 'express';
import { z } from 'zod';
import { createBadRequest } from '../errors';
import requireAdminSecret from '../middleware/adminSecret';
import { success } from '../responses';
import {
  createTenant,
  createTenantApiKey,
  getTenantUsageSummary,
  listTenants,
  revokeTenantApiKey,
  updateTenantStatus,
} from '../services/tenantService';
import { generateApiKey } from '../utils/apiKey';
import {
  archiveSubscriptionPlan,
  createSubscriptionPlan,
  listSubscriptionPlans,
  updateSubscriptionPlan,
} from '../services/subscriptionPlanService';
import { serializeSubscriptionPlan } from '../serializers/subscriptionPlan';

const router = Router();

router.use(requireAdminSecret);

const safeJsonParse = (payload: string) => {
  try {
    return JSON.parse(payload);
  } catch (error) {
    return payload;
  }
};

const serializeTenant = (tenant: any) => ({
  id: tenant.id,
  name: tenant.name,
  slug: tenant.slug,
  status: tenant.status,
  defaultContext: tenant.default_context,
  metadata: tenant.metadata ? safeJsonParse(tenant.metadata) : null,
  createdAt: tenant.created_at,
  updatedAt: tenant.updated_at,
});


router.get('/tenants', async (_req, res, next) => {
  try {
    const tenants = await listTenants();
    return res.json(success({ tenants: tenants.map(serializeTenant) }));
  } catch (error) {
    return next(error);
  }
});

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
    return res.json(success({ tenant: serializeTenant(tenant) }));
  } catch (error) {
    if (error instanceof z.ZodError) {
      return next(createBadRequest(error.errors[0]?.message || 'Invalid payload'));
    }
    return next(error);
  }
});

router.get('/tenants/:tenantId/usage', async (req, res, next) => {
  try {
    const tenantId = Number(req.params.tenantId);
    if (Number.isNaN(tenantId)) {
      throw createBadRequest('Invalid tenantId');
    }
    const usage = await getTenantUsageSummary(tenantId);
    return res.json(success({ usage }));
  } catch (error) {
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

const statusSchema = z.object({ status: z.enum(['active', 'suspended']) });

router.patch('/tenants/:tenantId/status', async (req, res, next) => {
  try {
    const tenantId = Number(req.params.tenantId);
    if (Number.isNaN(tenantId)) {
      throw createBadRequest('Invalid tenantId');
    }
    const body = statusSchema.parse(req.body ?? {});
    const tenant = await updateTenantStatus(tenantId, body.status);
    if (!tenant) {
      throw createBadRequest('Tenant not found');
    }
    return res.json(success({ tenant: serializeTenant(tenant) }));
  } catch (error) {
    if (error instanceof z.ZodError) {
      return next(createBadRequest(error.errors[0]?.message || 'Invalid payload'));
    }
    return next(error);
  }
});

const planModuleSchema = z.object({
  code: z.string().min(1).max(64),
  name: z.string().min(2).max(160),
  description: z.string().max(500).optional(),
  priceCents: z.number().int().nonnegative().optional(),
  limits: z.record(z.any()).optional(),
  included: z.boolean().optional(),
});

const currencySchema = z
  .string()
  .length(3)
  .regex(/^[A-Za-z]{3}$/)
  .transform((value) => value.toUpperCase());

const planCreateSchema = z.object({
  name: z.string().min(3).max(120),
  slug: z.string().regex(/^[a-z0-9-]+$/),
  description: z.string().max(600).nullable().optional(),
  billingPeriod: z.enum(['monthly', 'annual']).default('monthly'),
  currency: currencySchema.default('EUR'),
  priceCents: z.number().int().positive(),
  setupFeeCents: z.number().int().nonnegative().default(0),
  trialDays: z.number().int().min(0).max(365).default(0),
  modules: z.array(planModuleSchema).optional(),
  features: z.array(z.string().min(1)).optional(),
  metadata: z.record(z.any()).optional(),
  sortOrder: z.number().int().default(0),
  isActive: z.boolean().default(true),
});

const planUpdateSchema = z
  .object({
    name: z.string().min(3).max(120).optional(),
    slug: z.string().regex(/^[a-z0-9-]+$/).optional(),
    description: z.string().max(600).nullable().optional(),
    billingPeriod: z.enum(['monthly', 'annual']).optional(),
    currency: currencySchema.optional(),
    priceCents: z.number().int().positive().optional(),
    setupFeeCents: z.number().int().nonnegative().optional(),
    trialDays: z.number().int().min(0).max(365).optional(),
    modules: z.array(planModuleSchema).optional(),
    features: z.array(z.string().min(1)).optional(),
    metadata: z.record(z.any()).optional(),
    sortOrder: z.number().int().optional(),
    isActive: z.boolean().optional(),
  })
  .refine((value) => Object.keys(value).length > 0, {
    message: 'At least one field must be provided',
  });

router.get('/subscription-plans', async (req, res, next) => {
  try {
    const includeInactive = req.query.includeInactive === '1' || req.query.includeInactive === 'true';
    const includeArchived = req.query.includeArchived === '1' || req.query.includeArchived === 'true';
    const billingPeriod = req.query.billingPeriod;

    if (billingPeriod && billingPeriod !== 'monthly' && billingPeriod !== 'annual') {
      throw createBadRequest('Invalid billingPeriod value');
    }

    const plans = await listSubscriptionPlans({
      includeInactive,
      includeArchived,
      billingPeriod: billingPeriod as 'monthly' | 'annual' | undefined,
    });

    return res.json(success({ plans: plans.map(serializeSubscriptionPlan) }));
  } catch (error) {
    return next(error);
  }
});

router.post('/subscription-plans', async (req, res, next) => {
  try {
    const body = planCreateSchema.parse(req.body);
    const plan = await createSubscriptionPlan({
      name: body.name,
      slug: body.slug,
      description: body.description ?? null,
      billingPeriod: body.billingPeriod,
      currency: body.currency,
      priceCents: body.priceCents,
      setupFeeCents: body.setupFeeCents,
      trialDays: body.trialDays,
      modules: body.modules ?? null,
      features: body.features ?? null,
      metadata: body.metadata ?? null,
      sortOrder: body.sortOrder,
      isActive: body.isActive,
    });

    return res.status(201).json(success({ plan: serializeSubscriptionPlan(plan) }));
  } catch (error) {
    if (error instanceof z.ZodError) {
      return next(createBadRequest(error.errors[0]?.message || 'Invalid payload'));
    }
    return next(error);
  }
});

router.patch('/subscription-plans/:planId', async (req, res, next) => {
  try {
    const planId = Number(req.params.planId);
    if (Number.isNaN(planId)) {
      throw createBadRequest('Invalid planId');
    }

    const body = planUpdateSchema.parse(req.body ?? {});
    const plan = await updateSubscriptionPlan(planId, body);
    if (!plan) {
      throw createBadRequest('Plan not found');
    }

    return res.json(success({ plan: serializeSubscriptionPlan(plan) }));
  } catch (error) {
    if (error instanceof z.ZodError) {
      return next(createBadRequest(error.errors[0]?.message || 'Invalid payload'));
    }
    return next(error);
  }
});

router.delete('/subscription-plans/:planId', async (req, res, next) => {
  try {
    const planId = Number(req.params.planId);
    if (Number.isNaN(planId)) {
      throw createBadRequest('Invalid planId');
    }

    const plan = await archiveSubscriptionPlan(planId);
    if (!plan) {
      throw createBadRequest('Plan not found or already archived');
    }

    return res.json(success({ plan: serializeSubscriptionPlan(plan) }));
  } catch (error) {
    return next(error);
  }
});

export default router;
