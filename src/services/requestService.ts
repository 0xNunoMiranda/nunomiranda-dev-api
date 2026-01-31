import { OkPacket, ResultSetHeader, RowDataPacket } from 'mysql2';
import pool from '../db';

export type CreateRequestInput = {
  tenantId: number;
  customerName: string;
  customerPhone?: string | null;
  customerEmail?: string | null;
  source?: string | null;
  channel?: string | null;
  status?: string | null;
  preferredDate?: string | null;
  preferredTime?: string | null;
  notes?: string | null;
  metadata?: Record<string, unknown> | null;
};

export type ListRequestsFilters = {
  status?: string;
  from?: string | null;
  to?: string | null;
  query?: string;
  limit?: number;
  offset?: number;
};

export type UpdateRequestInput = {
  status?: string;
  notes?: string | null;
  preferredDate?: string | null;
  preferredTime?: string | null;
  metadata?: Record<string, unknown> | null;
};

interface RequestRow extends RowDataPacket {
  id: number;
  tenant_id: number;
  customer_name: string;
  customer_phone: string | null;
  customer_email: string | null;
  source: string;
  channel: string;
  status: string;
  preferred_date: string | null;
  preferred_time: string | null;
  notes: string | null;
  metadata: string | null;
  created_at: Date;
  updated_at: Date;
}

interface RequestEventRow extends RowDataPacket {
  id: number;
  tenant_id: number;
  request_id: number;
  event_type: string;
  payload: string | null;
  created_by: string | null;
  created_at: Date;
}

type ProcedureResult = RowDataPacket[][] | RowDataPacket[] | OkPacket | OkPacket[] | ResultSetHeader;

const isOkPacketArray = (value: unknown): value is OkPacket[] =>
  Array.isArray(value) && value.length > 0 && Object.prototype.hasOwnProperty.call(value[0], 'affectedRows');

const callProcedure = async <TRow extends RowDataPacket>(sql: string, params: unknown[]) => {
  const [resultSets] = await pool.query<ProcedureResult>(sql, params);
  const sets = Array.isArray(resultSets) ? resultSets : [resultSets];
  for (let i = sets.length - 1; i >= 0; i -= 1) {
    const rows = sets[i];
    if (Array.isArray(rows) && !isOkPacketArray(rows)) {
      return rows as TRow[];
    }
  }
  return [] as TRow[];
};

export const createRequest = async (input: CreateRequestInput) => {
  const rows = await callProcedure<RequestRow>('CALL sp_create_request(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
    input.tenantId,
    input.customerName,
    input.customerPhone ?? null,
    input.customerEmail ?? null,
    input.source ?? null,
    input.channel ?? null,
    input.status ?? null,
    input.preferredDate ?? null,
    input.preferredTime ?? null,
    input.notes ?? null,
    input.metadata ? JSON.stringify(input.metadata) : null,
  ]);
  return rows[0];
};

export const listRequests = async (tenantId: number, filters: ListRequestsFilters) => {
  const rows = await callProcedure<RequestRow>('CALL sp_list_requests(?, ?, ?, ?, ?, ?, ?)', [
    tenantId,
    filters.status ?? null,
    filters.from ?? null,
    filters.to ?? null,
    filters.query ?? null,
    filters.limit ?? 50,
    filters.offset ?? 0,
  ]);
  return rows;
};

export const getRequestById = async (tenantId: number, requestId: number) => {
  const rows = await callProcedure<RequestRow>('CALL sp_get_request_by_id(?, ?)', [tenantId, requestId]);
  return rows[0];
};

export const listRequestEvents = async (tenantId: number, requestId: number) => {
  const rows = await callProcedure<RequestEventRow>('CALL sp_list_request_events(?, ?)', [tenantId, requestId]);
  return rows;
};

export const updateRequest = async (
  tenantId: number,
  requestId: number,
  updates: UpdateRequestInput,
  actor: string | null,
) => {
  const rows = await callProcedure<RequestRow>('CALL sp_update_request(?, ?, ?, ?)', [
    tenantId,
    requestId,
    JSON.stringify(updates),
    actor,
  ]);
  return rows[0];
};

export const insertRequestEvent = async (
  tenantId: number,
  requestId: number,
  eventType: string,
  payload: Record<string, unknown> | null,
  actor: string | null,
) => {
  const rows = await callProcedure<RequestEventRow>('CALL sp_insert_request_event(?, ?, ?, ?, ?)', [
    tenantId,
    requestId,
    eventType,
    payload ? JSON.stringify(payload) : null,
    actor,
  ]);
  return rows[0];
};
