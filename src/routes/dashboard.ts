import { RequestHandler, Router } from 'express';
import { z } from 'zod';
import { createBadRequest } from '../errors';
import { requireTenantAuth } from '../middleware/tenantAuth';
import tenantRateLimit from '../middleware/tenantRateLimit';
import { success } from '../responses';
import { getDashboardSummary, listPendingRequests, listRequestsForDate } from '../services/dashboardService';

const router = Router();

const listQuerySchema = z.object({
  q: z.string().min(2).optional(),
  limit: z.coerce.number().int().min(1).max(100).optional(),
});

const formatDateOnly = (date: Date) => date.toISOString().slice(0, 10);

const dateWithOffset = (offsetDays: number) => {
  const base = new Date();
  base.setUTCHours(0, 0, 0, 0);
  base.setUTCDate(base.getUTCDate() + offsetDays);
  return formatDateOnly(base);
};

router.get('/summary', requireTenantAuth(['requests:read']), tenantRateLimit, async (req, res, next) => {
  try {
    const tenantId = req.tenant!.tenantId;
    const summary = await getDashboardSummary(tenantId);
    return res.json(success({ summary }));
  } catch (error) {
    return next(error);
  }
});

router.get('/pending', requireTenantAuth(['requests:read']), tenantRateLimit, async (req, res, next) => {
  try {
    const query = listQuerySchema.parse(req.query);
    const tenantId = req.tenant!.tenantId;
    const requests = await listPendingRequests(tenantId, query.q, query.limit);
    return res.json(success({ requests }));
  } catch (error) {
    if (error instanceof z.ZodError) {
      return next(createBadRequest(error.errors[0]?.message || 'Invalid filters'));
    }
    return next(error);
  }
});

const buildDayHandler = (offsetDays: number): RequestHandler =>
  async (req, res, next) => {
    try {
      const query = listQuerySchema.parse(req.query);
      const tenantId = req.tenant!.tenantId;
      const targetDate = dateWithOffset(offsetDays);
      const requests = await listRequestsForDate(tenantId, targetDate, query.q, query.limit);
      return res.json(success({ requests, date: targetDate }));
    } catch (error) {
      if (error instanceof z.ZodError) {
        return next(createBadRequest(error.errors[0]?.message || 'Invalid filters'));
      }
      return next(error);
    }
  };

router.get('/today', requireTenantAuth(['requests:read']), tenantRateLimit, buildDayHandler(0));
router.get('/tomorrow', requireTenantAuth(['requests:read']), tenantRateLimit, buildDayHandler(1));

export default router;
