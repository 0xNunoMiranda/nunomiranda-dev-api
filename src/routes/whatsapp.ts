import { Router } from 'express';
import { z } from 'zod';
import { createBadRequest } from '../errors';
import { success } from '../responses';
import { requireTenantAuth } from '../middleware/tenantAuth';
import whatsappWebService from '../services/whatsappWebService';

const router = Router();

const getBearerToken = (authHeader: string | undefined) => {
  if (!authHeader) return null;
  const [type, value] = authHeader.split(' ');
  if (type?.toLowerCase() !== 'bearer' || !value) return null;
  return value.trim();
};

router.get('/status', requireTenantAuth(['requests:read']), async (req, res, next) => {
  try {
    const token = getBearerToken(req.header('authorization'));
    if (!token) throw createBadRequest('Missing bearer token');
    const status = await whatsappWebService.status(token);
    return res.json(success({ status }));
  } catch (error) {
    return next(error);
  }
});

const connectSchema = z.object({
  siteUrl: z.string().url().optional(),
  messagesPerMinute: z.number().int().min(1).max(5).optional(),
  promptTemplate: z.string().max(4000).optional(),
  tags: z
    .object({
      bookings: z.boolean().optional(),
      faqs: z.boolean().optional(),
      shop: z.boolean().optional(),
    })
    .optional(),
});

router.post('/connect', requireTenantAuth(['requests:write']), async (req, res, next) => {
  try {
    const token = getBearerToken(req.header('authorization'));
    if (!token) throw createBadRequest('Missing bearer token');
    const body = connectSchema.parse(req.body ?? {});
    const tenantId = req.tenant?.tenantId;
    if (!tenantId) throw createBadRequest('Missing tenant context');

    const result = await whatsappWebService.connect(token, tenantId, {
      siteUrl: body.siteUrl,
      messagesPerMinute: body.messagesPerMinute,
      promptTemplate: body.promptTemplate,
      tags: body.tags as any,
    });

    return res.json(success({ ...result }));
  } catch (error) {
    if (error instanceof z.ZodError) {
      return next(createBadRequest(error.errors[0]?.message || 'Invalid payload'));
    }
    return next(error);
  }
});

router.post('/disconnect', requireTenantAuth(['requests:write']), async (req, res, next) => {
  try {
    const token = getBearerToken(req.header('authorization'));
    if (!token) throw createBadRequest('Missing bearer token');
    const result = await whatsappWebService.disconnect(token);
    return res.json(success({ ...result }));
  } catch (error) {
    return next(error);
  }
});

export default router;

