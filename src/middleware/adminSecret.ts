import { NextFunction, Request, Response } from 'express';
import config from '../config';
import { failure } from '../responses';

export default function requireAdminSecret(req: Request, res: Response, next: NextFunction) {
  const headerSecret = req.header('x-admin-secret') || req.header('authorization');
  if (!headerSecret || headerSecret !== config.ADMIN_SECRET) {
    return res.status(401).json(failure('unauthorized', 'Invalid admin secret'));
  }
  return next();
}
