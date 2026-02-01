import { Router } from 'express';
import { z } from 'zod';
import { createBadRequest } from '../errors';
import { success } from '../responses';
import { serializeSubscriptionPlan } from '../serializers/subscriptionPlan';
import { listSubscriptionPlans } from '../services/subscriptionPlanService';

const router = Router();

const catalogQuerySchema = z.object({
  billingPeriod: z.enum(['monthly', 'annual']).optional(),
});

router.get('/plans', async (req, res, next) => {
  try {
    const filters = catalogQuerySchema.parse(req.query ?? {});
    const plans = await listSubscriptionPlans({
      includeInactive: false,
      includeArchived: false,
      billingPeriod: filters.billingPeriod,
    });

    return res.json(success({ plans: plans.map(serializeSubscriptionPlan) }));
  } catch (error) {
    if (error instanceof z.ZodError) {
      return next(createBadRequest(error.errors[0]?.message || 'Invalid query'));
    }
    return next(error);
  }
});

export default router;
