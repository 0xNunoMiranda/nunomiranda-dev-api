import { RowDataPacket } from 'mysql2';
import pool from '../db';

interface TenantRow extends RowDataPacket {
  id: number;
  name: string;
  slug: string;
  status: string;
  default_context: string | null;
  metadata: string | null;
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

export const findTenantKeyByPublicId = async (publicId: string) => {
  const rows = await executeProcedure<TenantApiKeyRow>('CALL sp_lookup_api_key(?)', [publicId]);
  return rows[0];
};

export const findTenantById = async (tenantId: number) => {
  const [rows] = await pool.query<TenantRow[]>('SELECT * FROM tenants WHERE id = ?', [tenantId]);
  return rows[0];
};
