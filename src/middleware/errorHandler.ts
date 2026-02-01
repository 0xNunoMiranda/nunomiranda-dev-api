import { NextFunction, Request, Response, RequestHandler } from 'express';
import { ApiError } from '../errors';
import logger from '../logger';
import { failure } from '../responses';
import { z, ZodSchema } from 'zod';

export default function errorHandler(err: unknown, _req: Request, res: Response, _next: NextFunction) {
  if (err instanceof ApiError) {
    return res.status(err.statusCode).json(failure(err.code, err.message));
  }

  logger.error({ err }, 'Unhandled error');
  return res.status(500).json(failure('internal_error', 'Unexpected server error'));
}

/**
 * Async handler wrapper para evitar try/catch em todos os handlers
 */
export function asyncHandler(fn: (req: Request, res: Response, next: NextFunction) => Promise<any>): RequestHandler {
  return (req, res, next) => {
    Promise.resolve(fn(req, res, next)).catch(next);
  };
}

/**
 * Middleware de validação com Zod
 */
export function validateRequest(schema: ZodSchema): RequestHandler {
  return (req: Request, res: Response, next: NextFunction) => {
    try {
      schema.parse({
        body: req.body,
        query: req.query,
        params: req.params,
      });
      next();
    } catch (error) {
      if (error instanceof z.ZodError) {
        return res.status(400).json({
          error: 'Validation failed',
          details: error.errors,
        });
      }
      next(error);
    }
  };
}
