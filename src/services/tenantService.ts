import { ResultSetHeader, RowDataPacket } from 'mysql2';
import pool from '../db';

type TenantStatus = 'active' | 'suspended';

interface TenantRow extends RowDataPacket {
  id: number;
  name: string;
  slug: string;
  status: TenantStatus;
  default_context: string | null;
  metadata: string | null;
  created_at: Date;
  updated_at: Date;
}

interface TenantApiKeyRow extends RowDataPacket {
  id: number;
  tenant_id: number;
  public_id: string;
  label: string | null;
  key_hash: string;
  salt: string;
  scopes_json: string;
  last_used_at: Date | null;
  revoked_at: Date | null;
  tenant_status?: string;
}

export type TenantInput = {
  name: string;
  slug: string;
  defaultContext?: string | null;
  metadata?: Record<string, unknown> | null;
};

export type TenantApiKeyInput = {
  tenantId: number;
  publicId: string;
  label?: string | null;
  keyHash: string;
  salt: string;
  scopes: string[];
};

const executeProcedure = async <TRow>(query: string, params: unknown[]) => {
  const [resultSets] = await pool.query<RowDataPacket[][]>(query, params);
  const [rows] = resultSets;
  return rows as TRow[];
};

export const createTenant = async (input: TenantInput) => {
  const rows = await executeProcedure<TenantRow>('CALL sp_create_tenant(?, ?, ?, ?)', [
    input.name,
    input.slug,
    input.defaultContext ?? null,
    input.metadata ? JSON.stringify(input.metadata) : null,
  ]);
  return rows[0];
};

export const createTenantApiKey = async (input: TenantApiKeyInput) => {
  const rows = await executeProcedure<TenantApiKeyRow>('CALL sp_create_tenant_api_key(?, ?, ?, ?, ?, ?)', [
    input.tenantId,
    input.publicId,
    input.label ?? null,
    input.keyHash,
    input.salt,
    JSON.stringify(input.scopes),
  ]);
  return rows[0];
};

export const revokeTenantApiKey = async (keyId: number) => {
  const rows = await executeProcedure<TenantApiKeyRow>('CALL sp_revoke_api_key(?)', [keyId]);
  return rows[0];
};

export const listTenantApiKeys = async (tenantId: number, opts?: { includeRevoked?: boolean }) => {
  const includeRevoked = opts?.includeRevoked ?? true;
  const whereRevoked = includeRevoked ? '' : ' AND revoked_at IS NULL';

  const [rows] = await pool.query<TenantApiKeyRow[]>(
    `SELECT *
     FROM tenant_api_keys
     WHERE tenant_id = ?${whereRevoked}
     ORDER BY created_at DESC
     LIMIT 200`,
    [tenantId],
  );

  return rows;
};

export const revokeOtherTenantApiKeys = async (tenantId: number, keepKeyId: number) => {
  const [result] = await pool.query<ResultSetHeader>(
    `UPDATE tenant_api_keys
     SET revoked_at = NOW()
     WHERE tenant_id = ? AND revoked_at IS NULL AND id <> ?`,
    [tenantId, keepKeyId],
  );

  return result.affectedRows;
};

export const findTenantKeyByPublicId = async (publicId: string) => {
  const rows = await executeProcedure<TenantApiKeyRow>('CALL sp_lookup_api_key(?)', [publicId]);
  return rows[0];
};

export const findTenantById = async (tenantId: number) => {
  const [rows] = await pool.query<TenantRow[]>('SELECT * FROM tenants WHERE id = ?', [tenantId]);
  return rows[0];
};

export const listTenants = async () => {
  const [rows] = await pool.query<TenantRow[]>(
    'SELECT * FROM tenants ORDER BY created_at DESC LIMIT 200',
  );
  return rows;
};

export const updateTenantStatus = async (tenantId: number, status: TenantStatus) => {
  const [result] = await pool.query<ResultSetHeader>('UPDATE tenants SET status = ? WHERE id = ?', [status, tenantId]);
  if (result.affectedRows === 0) {
    return null;
  }
  return findTenantById(tenantId);
};

interface RequestTotalsRow extends RowDataPacket {
  total: number | null;
  last24h: number | null;
  last7d: number | null;
}

interface StatusBreakdownRow extends RowDataPacket {
  status: string;
  count: number;
}

interface RateWindowRow extends RowDataPacket {
  window_start: Date;
  window_seconds: number;
  request_count: number;
  updated_at: Date;
}

interface RateStatsRow extends RowDataPacket {
  requests_today: number | null;
  windows_today: number | null;
}

export type TenantUsageSummary = {
  totals: { total: number; last24h: number; last7d: number };
  statusBreakdown: Array<{ status: string; count: number }>;
  rateLimiter: {
    windows: Array<{
      windowStart: string;
      windowSeconds: number;
      requestCount: number;
      updatedAt: string;
    }>;
    today: { windows: number; requests: number };
    latestResetAt: number | null;
  };
};

export const getTenantUsageSummary = async (tenantId: number): Promise<TenantUsageSummary> => {
  const [[totalsRow]] = await pool.query<RequestTotalsRow[]>(
    `SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN created_at >= NOW() - INTERVAL 1 DAY THEN 1 ELSE 0 END) AS last24h,
        SUM(CASE WHEN created_at >= NOW() - INTERVAL 7 DAY THEN 1 ELSE 0 END) AS last7d
      FROM requests
      WHERE tenant_id = ?`,
    [tenantId],
  );

  const [statusRows] = await pool.query<StatusBreakdownRow[]>(
    'SELECT status, COUNT(*) AS count FROM requests WHERE tenant_id = ? GROUP BY status ORDER BY count DESC',
    [tenantId],
  );

  const [rateRows] = await pool.query<RateWindowRow[]>(
    `SELECT window_start, window_seconds, request_count, updated_at
     FROM tenant_rate_limit_windows
     WHERE tenant_id = ?
     ORDER BY window_start DESC
     LIMIT 12`,
    [tenantId],
  );

  const [[rateStats]] = await pool.query<RateStatsRow[]>(
    `SELECT
        COALESCE(SUM(request_count), 0) AS requests_today,
        COUNT(*) AS windows_today
      FROM tenant_rate_limit_windows
      WHERE tenant_id = ?
        AND DATE(window_start) = CURDATE()`,
    [tenantId],
  );

  const latestWindow = rateRows[0];
  const latestResetAt = latestWindow
    ? Math.floor(latestWindow.window_start.getTime() / 1000) + latestWindow.window_seconds
    : null;

  return {
    totals: {
      total: totalsRow?.total ?? 0,
      last24h: totalsRow?.last24h ?? 0,
      last7d: totalsRow?.last7d ?? 0,
    },
    statusBreakdown: statusRows.map((row) => ({ status: row.status, count: row.count })),
    rateLimiter: {
      windows: rateRows.map((row) => ({
        windowStart: row.window_start.toISOString(),
        windowSeconds: row.window_seconds,
        requestCount: row.request_count,
        updatedAt: row.updated_at.toISOString(),
      })),
      today: {
        windows: rateStats?.windows_today ?? 0,
        requests: rateStats?.requests_today ?? 0,
      },
      latestResetAt,
    },
  };
};
