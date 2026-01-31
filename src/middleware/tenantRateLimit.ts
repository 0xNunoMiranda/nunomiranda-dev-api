import { NextFunction, Request, Response } from 'express';
import config from '../config';
import { failure } from '../responses';

const buckets = new Map<number, { count: number; resetsAt: number }>();

const WINDOW_MS = 60 * 1000;

export default function tenantRateLimit(req: Request, res: Response, next: NextFunction) {
  const tenantId = req.tenant?.tenantId;
  if (!tenantId) {
    return next();
  }

  const now = Date.now();
  const bucket = buckets.get(tenantId);
  if (!bucket || bucket.resetsAt <= now) {
    buckets.set(tenantId, { count: 1, resetsAt: now + WINDOW_MS });
    return next();
  }

  if (bucket.count >= config.HTTP_RATE_LIMIT_PER_MIN) {
    const retryAfter = Math.ceil((bucket.resetsAt - now) / 1000);
    res.setHeader('Retry-After', retryAfter);
    return res.status(429).json(failure('rate_limited', 'Rate limit exceeded for tenant'));
  }

  bucket.count += 1;
  return next();
}
