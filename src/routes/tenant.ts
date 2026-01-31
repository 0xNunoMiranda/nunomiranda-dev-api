import { Router } from 'express';
import { requireTenantAuth } from '../middleware/tenantAuth';
import tenantRateLimit from '../middleware/tenantRateLimit';
import { failure, success } from '../responses';
import { findTenantById } from '../services/tenantService';

const router = Router();

router.get('/tenants/current', requireTenantAuth(['requests:read']), tenantRateLimit, async (req, res, next) => {
  try {
    const tenantId = req.tenant?.tenantId;
    if (!tenantId) {
      return res.status(401).json(failure('unauthorized', 'Missing tenant context'));
    }
    const tenant = await findTenantById(tenantId);
    return res.json(success({ tenant }));
  } catch (error) {
    next(error);
  }
});

export default router;
