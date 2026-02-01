import { NextFunction, Request, Response } from 'express';
import config from '../config';
import { failure } from '../responses';
import { checkTenantRateLimit } from '../services/rateLimitService';

const buildHeaders = (res: Response, remaining: number, resetAt: number) => {
  res.setHeader('X-RateLimit-Limit', config.HTTP_RATE_LIMIT_PER_MIN);
  res.setHeader('X-RateLimit-Remaining', Math.max(remaining, 0));
  res.setHeader('X-RateLimit-Reset', resetAt);
};

export default async function tenantRateLimit(req: Request, res: Response, next: NextFunction) {
  const tenantId = req.tenant?.tenantId;
  if (!tenantId) {
    return next();
  }

  try {
    const result = await checkTenantRateLimit(
      tenantId,
      config.HTTP_RATE_LIMIT_WINDOW_SECONDS,
      config.HTTP_RATE_LIMIT_PER_MIN,
    );

    if (!result || result.allowed === 0) {
      const nowSeconds = Math.floor(Date.now() / 1000);
      const resetAt = result?.reset_at ?? nowSeconds + config.HTTP_RATE_LIMIT_WINDOW_SECONDS;
      const retryAfter = Math.max(resetAt - nowSeconds, 1);
      buildHeaders(res, 0, resetAt);
      res.setHeader('Retry-After', retryAfter);
      return res.status(429).json(failure('rate_limited', 'Rate limit exceeded for tenant'));
    }

    buildHeaders(res, result.remaining, result.reset_at);
    return next();
  } catch (error) {
    return next(error);
  }
}
