import { RowDataPacket } from 'mysql2';
import { callProcedure } from './procedure';

interface DashboardSummaryRow extends RowDataPacket {
  pending_count: number;
  today_count: number;
  tomorrow_count: number;
}

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

export const getDashboardSummary = async (tenantId: number) => {
  const rows = await callProcedure<DashboardSummaryRow>('CALL sp_dashboard_summary(?)', [tenantId]);
  return rows[0] || { pending_count: 0, today_count: 0, tomorrow_count: 0 };
};

export const listPendingRequests = async (tenantId: number, query?: string, limit?: number) =>
  callProcedure<RequestRow>('CALL sp_dashboard_pending(?, ?, ?)', [tenantId, query ?? null, limit ?? 25]);

export const listRequestsForDate = async (
  tenantId: number,
  date: string,
  query?: string,
  limit?: number,
) => callProcedure<RequestRow>('CALL sp_dashboard_by_date(?, ?, ?, ?)', [tenantId, date, query ?? null, limit ?? 25]);
