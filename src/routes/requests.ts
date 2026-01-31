import { Router } from 'express';
import { z } from 'zod';
import { createBadRequest, createNotFound } from '../errors';
import { requireTenantAuth } from '../middleware/tenantAuth';
import tenantRateLimit from '../middleware/tenantRateLimit';
import { success } from '../responses';
import {
  createRequest,
  getRequestById,
  insertRequestEvent,
  listRequestEvents,
  listRequests,
  updateRequest,
} from '../services/requestService';
import type { UpdateRequestInput } from '../services/requestService';

const router = Router();

const statusEnum = z.enum(['new', 'pending', 'confirmed', 'done', 'cancelled']);

const createSchema = z.object({
  customerName: z.string().min(2).max(120),
  customerPhone: z.string().min(6).max(32).optional(),
  customerEmail: z.string().email().optional(),
  source: z.string().max(32).optional(),
  channel: z.string().max(32).optional(),
  status: statusEnum.optional(),
  preferredDate: z.coerce.date().optional(),
  preferredTime: z.string().max(16).optional(),
  notes: z.string().max(2000).optional(),
  metadata: z.record(z.any()).optional(),
});

const updateSchema = z
  .object({
    status: statusEnum.optional(),
    notes: z.string().max(2000).nullable().optional(),
    preferredDate: z.coerce.date().nullable().optional(),
    preferredTime: z.string().max(16).nullable().optional(),
    metadata: z.record(z.any()).nullable().optional(),
  })
  .refine((val) => Object.keys(val).length > 0, { message: 'Provide at least one field to update' });

const querySchema = z.object({
  status: statusEnum.optional(),
  from: z.coerce.date().optional(),
  to: z.coerce.date().optional(),
  q: z.string().min(2).optional(),
  limit: z.coerce.number().int().min(1).max(200).optional(),
  offset: z.coerce.number().int().min(0).optional(),
});

const manualEventSchema = z.object({
  eventType: z.string().min(3).max(40),
  payload: z.record(z.any()).optional(),
});

const formatDate = (date?: Date | null) => (date ? date.toISOString().slice(0, 10) : undefined);

const formatDateTime = (date?: Date | null) => (date ? date.toISOString().slice(0, 19).replace('T', ' ') : undefined);

router.post('/', requireTenantAuth(['requests:write']), tenantRateLimit, async (req, res, next) => {
  try {
    const body = createSchema.parse(req.body);
    const tenantId = req.tenant!.tenantId;

    const request = await createRequest({
      tenantId,
      customerName: body.customerName,
      customerPhone: body.customerPhone ?? null,
      customerEmail: body.customerEmail ?? null,
      source: body.source ?? null,
      channel: body.channel ?? null,
      status: body.status ?? null,
      preferredDate: formatDate(body.preferredDate),
      preferredTime: body.preferredTime ?? null,
      notes: body.notes ?? null,
      metadata: body.metadata ?? null,
    });

    return res.status(201).json(success({ request }));
  } catch (error) {
    if (error instanceof z.ZodError) {
      return next(createBadRequest(error.errors[0]?.message || 'Invalid payload'));
    }
    return next(error);
  }
});

router.get('/', requireTenantAuth(['requests:read']), tenantRateLimit, async (req, res, next) => {
  try {
    const query = querySchema.parse(req.query);
    const tenantId = req.tenant!.tenantId;

    const requests = await listRequests(tenantId, {
      status: query.status,
      from: formatDateTime(query.from),
      to: formatDateTime(query.to),
      query: query.q,
      limit: query.limit,
      offset: query.offset,
    });

    return res.json(success({ requests }));
  } catch (error) {
    if (error instanceof z.ZodError) {
      return next(createBadRequest(error.errors[0]?.message || 'Invalid filters'));
    }
    return next(error);
  }
});

router.get('/:id', requireTenantAuth(['requests:read']), tenantRateLimit, async (req, res, next) => {
  try {
    const requestId = Number(req.params.id);
    if (Number.isNaN(requestId)) {
      throw createBadRequest('Invalid request id');
    }
    const tenantId = req.tenant!.tenantId;
    const request = await getRequestById(tenantId, requestId);
    if (!request) {
      throw createNotFound('Request not found');
    }
    const events = await listRequestEvents(tenantId, requestId);
    return res.json(success({ request, events }));
  } catch (error) {
    return next(error);
  }
});

router.patch('/:id', requireTenantAuth(['requests:write']), tenantRateLimit, async (req, res, next) => {
  try {
    const requestId = Number(req.params.id);
    if (Number.isNaN(requestId)) {
      throw createBadRequest('Invalid request id');
    }
    const body = updateSchema.parse(req.body ?? {});
    const tenantId = req.tenant!.tenantId;

    const updates: UpdateRequestInput = {};
    if (body.status !== undefined) updates.status = body.status;
    if (body.notes !== undefined) updates.notes = body.notes;
    if (body.preferredDate !== undefined) updates.preferredDate = formatDate(body.preferredDate);
    if (body.preferredTime !== undefined) updates.preferredTime = body.preferredTime;
    if (body.metadata !== undefined) updates.metadata = body.metadata;

    const request = await updateRequest(tenantId, requestId, updates, 'api');

    if (!request) {
      throw createNotFound('Request not found');
    }

    return res.json(success({ request }));
  } catch (error) {
    if (error instanceof z.ZodError) {
      return next(createBadRequest(error.errors[0]?.message || 'Invalid payload'));
    }
    if ((error as Error & { sqlMessage?: string; code?: string }).sqlMessage === 'request_not_found') {
      return next(createNotFound('Request not found'));
    }
    return next(error);
  }
});

router.post('/:id/events', requireTenantAuth(['requests:write']), tenantRateLimit, async (req, res, next) => {
  try {
    const requestId = Number(req.params.id);
    if (Number.isNaN(requestId)) {
      throw createBadRequest('Invalid request id');
    }
    const body = manualEventSchema.parse(req.body);
    const tenantId = req.tenant!.tenantId;

    const request = await getRequestById(tenantId, requestId);
    if (!request) {
      throw createNotFound('Request not found');
    }

    const event = await insertRequestEvent(tenantId, requestId, body.eventType, body.payload ?? null, 'api');
    return res.status(201).json(success({ event }));
  } catch (error) {
    if (error instanceof z.ZodError) {
      return next(createBadRequest(error.errors[0]?.message || 'Invalid payload'));
    }
    return next(error);
  }
});

export default router;
