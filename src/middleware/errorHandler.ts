import { NextFunction, Request, Response } from 'express';
import { ApiError } from '../errors';
import logger from '../logger';
import { failure } from '../responses';

export default function errorHandler(err: unknown, _req: Request, res: Response, _next: NextFunction) {
  if (err instanceof ApiError) {
    return res.status(err.statusCode).json(failure(err.code, err.message));
  }

  logger.error({ err }, 'Unhandled error');
  return res.status(500).json(failure('internal_error', 'Unexpected server error'));
}
