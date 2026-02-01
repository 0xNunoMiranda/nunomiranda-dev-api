import { RowDataPacket } from 'mysql2';
import { callProcedure } from './procedure';

export interface RateLimitRow extends RowDataPacket {
  allowed: number;
  remaining: number;
  reset_at: number;
}

export const checkTenantRateLimit = async (
  tenantId: number,
  windowSeconds: number,
  maxRequests: number,
): Promise<RateLimitRow | null> => {
  const rows = await callProcedure<RateLimitRow>('CALL sp_check_rate_limit(?, ?, ?)', [
    tenantId,
    windowSeconds,
    maxRequests,
  ]);
  return rows[0] ?? null;
};
