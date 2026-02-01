import { ResultSetHeader, RowDataPacket } from 'mysql2';
import pool from '../db';

export type SubscriptionStatus = 'pending' | 'active' | 'paused' | 'cancelled' | 'expired' | 'failed';
export type ChargeStatus = 'pending' | 'processing' | 'paid' | 'failed' | 'refunded' | 'cancelled';
export type PaymentMethod = 'cc' | 'dd' | 'mbway' | 'multibanco' | 'google_pay' | 'apple_pay';
export type BillingPeriod = 'monthly' | 'annual';

// ─────────────────────────────────────────────────────────────────────────────
// Subscription Types
// ─────────────────────────────────────────────────────────────────────────────

export interface TenantSubscription {
  id: number;
  tenantId: number;
  planId: number;
  easypaySubscriptionId: string | null;
  easypayFrequentId: string | null;
  status: SubscriptionStatus;
  paymentMethod: PaymentMethod;
  billingPeriod: BillingPeriod;
  currency: string;
  amountCents: number;
  nextBillingAt: string | null;
  trialEndsAt: string | null;
  startedAt: string | null;
  cancelledAt: string | null;
  expiresAt: string | null;
  customerName: string | null;
  customerEmail: string | null;
  customerPhone: string | null;
  customerFiscalNumber: string | null;
  sddIban: string | null;
  sddMandateId: string | null;
  metadata: Record<string, unknown> | null;
  createdAt: string;
  updatedAt: string;
}

interface SubscriptionRow extends RowDataPacket {
  id: number;
  tenant_id: number;
  plan_id: number;
  easypay_subscription_id: string | null;
  easypay_frequent_id: string | null;
  status: SubscriptionStatus;
  payment_method: PaymentMethod;
  billing_period: BillingPeriod;
  currency: string;
  amount_cents: number;
  next_billing_at: Date | null;
  trial_ends_at: Date | null;
  started_at: Date | null;
  cancelled_at: Date | null;
  expires_at: Date | null;
  customer_name: string | null;
  customer_email: string | null;
  customer_phone: string | null;
  customer_fiscal_number: string | null;
  sdd_iban: string | null;
  sdd_mandate_id: string | null;
  metadata: string | null;
  created_at: Date;
  updated_at: Date;
}

export interface CreateSubscriptionInput {
  tenantId: number;
  planId: number;
  paymentMethod: PaymentMethod;
  billingPeriod: BillingPeriod;
  currency: string;
  amountCents: number;
  trialDays?: number;
  customerName?: string | null;
  customerEmail?: string | null;
  customerPhone?: string | null;
  customerFiscalNumber?: string | null;
  sddIban?: string | null;
  metadata?: Record<string, unknown> | null;
}

export interface UpdateSubscriptionInput {
  easypaySubscriptionId?: string | null;
  easypayFrequentId?: string | null;
  status?: SubscriptionStatus;
  nextBillingAt?: Date | null;
  startedAt?: Date | null;
  cancelledAt?: Date | null;
  expiresAt?: Date | null;
  sddMandateId?: string | null;
  metadata?: Record<string, unknown> | null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Charge Types
// ─────────────────────────────────────────────────────────────────────────────

export interface SubscriptionCharge {
  id: number;
  subscriptionId: number;
  tenantId: number;
  easypayTransactionId: string | null;
  easypayCaptureId: string | null;
  status: ChargeStatus;
  paymentMethod: PaymentMethod;
  currency: string;
  amountCents: number;
  paidAt: string | null;
  failedAt: string | null;
  failureReason: string | null;
  mbEntity: string | null;
  mbReference: string | null;
  mbExpiresAt: string | null;
  metadata: Record<string, unknown> | null;
  createdAt: string;
  updatedAt: string;
}

interface ChargeRow extends RowDataPacket {
  id: number;
  subscription_id: number;
  tenant_id: number;
  easypay_transaction_id: string | null;
  easypay_capture_id: string | null;
  status: ChargeStatus;
  payment_method: PaymentMethod;
  currency: string;
  amount_cents: number;
  paid_at: Date | null;
  failed_at: Date | null;
  failure_reason: string | null;
  mb_entity: string | null;
  mb_reference: string | null;
  mb_expires_at: Date | null;
  metadata: string | null;
  created_at: Date;
  updated_at: Date;
}

export interface CreateChargeInput {
  subscriptionId: number;
  tenantId: number;
  paymentMethod: PaymentMethod;
  currency: string;
  amountCents: number;
  easypayTransactionId?: string | null;
  mbEntity?: string | null;
  mbReference?: string | null;
  mbExpiresAt?: Date | null;
  metadata?: Record<string, unknown> | null;
}

export interface UpdateChargeInput {
  easypayTransactionId?: string | null;
  easypayCaptureId?: string | null;
  status?: ChargeStatus;
  paidAt?: Date | null;
  failedAt?: Date | null;
  failureReason?: string | null;
  mbEntity?: string | null;
  mbReference?: string | null;
  mbExpiresAt?: Date | null;
  metadata?: Record<string, unknown> | null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Webhook Types
// ─────────────────────────────────────────────────────────────────────────────

export interface EasypayWebhookLog {
  id: number;
  eventType: string;
  easypayId: string | null;
  payload: string;
  processedAt: string | null;
  errorMessage: string | null;
  createdAt: string;
}

interface WebhookRow extends RowDataPacket {
  id: number;
  event_type: string;
  easypay_id: string | null;
  payload: string;
  processed_at: Date | null;
  error_message: string | null;
  created_at: Date;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

const parseJson = <T>(value: string | null): T | null => {
  if (!value) return null;
  try {
    return JSON.parse(value) as T;
  } catch {
    return null;
  }
};

const stringifyJson = (value?: unknown | null): string | null => {
  if (value === undefined || value === null) return null;
  return JSON.stringify(value);
};

const dateToIso = (date: Date | null): string | null => {
  return date ? date.toISOString() : null;
};

const mapSubscriptionRow = (row: SubscriptionRow): TenantSubscription => ({
  id: row.id,
  tenantId: row.tenant_id,
  planId: row.plan_id,
  easypaySubscriptionId: row.easypay_subscription_id,
  easypayFrequentId: row.easypay_frequent_id,
  status: row.status,
  paymentMethod: row.payment_method,
  billingPeriod: row.billing_period,
  currency: row.currency,
  amountCents: row.amount_cents,
  nextBillingAt: dateToIso(row.next_billing_at),
  trialEndsAt: dateToIso(row.trial_ends_at),
  startedAt: dateToIso(row.started_at),
  cancelledAt: dateToIso(row.cancelled_at),
  expiresAt: dateToIso(row.expires_at),
  customerName: row.customer_name,
  customerEmail: row.customer_email,
  customerPhone: row.customer_phone,
  customerFiscalNumber: row.customer_fiscal_number,
  sddIban: row.sdd_iban,
  sddMandateId: row.sdd_mandate_id,
  metadata: parseJson(row.metadata),
  createdAt: row.created_at.toISOString(),
  updatedAt: row.updated_at.toISOString(),
});

const mapChargeRow = (row: ChargeRow): SubscriptionCharge => ({
  id: row.id,
  subscriptionId: row.subscription_id,
  tenantId: row.tenant_id,
  easypayTransactionId: row.easypay_transaction_id,
  easypayCaptureId: row.easypay_capture_id,
  status: row.status,
  paymentMethod: row.payment_method,
  currency: row.currency,
  amountCents: row.amount_cents,
  paidAt: dateToIso(row.paid_at),
  failedAt: dateToIso(row.failed_at),
  failureReason: row.failure_reason,
  mbEntity: row.mb_entity,
  mbReference: row.mb_reference,
  mbExpiresAt: dateToIso(row.mb_expires_at),
  metadata: parseJson(row.metadata),
  createdAt: row.created_at.toISOString(),
  updatedAt: row.updated_at.toISOString(),
});

const mapWebhookRow = (row: WebhookRow): EasypayWebhookLog => ({
  id: row.id,
  eventType: row.event_type,
  easypayId: row.easypay_id,
  payload: row.payload,
  processedAt: dateToIso(row.processed_at),
  errorMessage: row.error_message,
  createdAt: row.created_at.toISOString(),
});

// ─────────────────────────────────────────────────────────────────────────────
// Subscription CRUD
// ─────────────────────────────────────────────────────────────────────────────

export const createSubscription = async (input: CreateSubscriptionInput): Promise<TenantSubscription> => {
  const trialEndsAt = input.trialDays && input.trialDays > 0
    ? new Date(Date.now() + input.trialDays * 24 * 60 * 60 * 1000)
    : null;

  const [result] = await pool.query<ResultSetHeader>(
    `INSERT INTO tenant_subscriptions
      (tenant_id, plan_id, payment_method, billing_period, currency, amount_cents,
       trial_ends_at, customer_name, customer_email, customer_phone, customer_fiscal_number, sdd_iban, metadata)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
    [
      input.tenantId,
      input.planId,
      input.paymentMethod,
      input.billingPeriod,
      input.currency,
      input.amountCents,
      trialEndsAt,
      input.customerName ?? null,
      input.customerEmail ?? null,
      input.customerPhone ?? null,
      input.customerFiscalNumber ?? null,
      input.sddIban ?? null,
      stringifyJson(input.metadata),
    ],
  );

  return (await findSubscriptionById(result.insertId))!;
};

export const findSubscriptionById = async (id: number): Promise<TenantSubscription | null> => {
  const [rows] = await pool.query<SubscriptionRow[]>(
    'SELECT * FROM tenant_subscriptions WHERE id = ? LIMIT 1',
    [id],
  );
  return rows[0] ? mapSubscriptionRow(rows[0]) : null;
};

export const findSubscriptionByTenantId = async (tenantId: number): Promise<TenantSubscription | null> => {
  const [rows] = await pool.query<SubscriptionRow[]>(
    `SELECT * FROM tenant_subscriptions
     WHERE tenant_id = ? AND status NOT IN ('cancelled', 'expired')
     ORDER BY created_at DESC LIMIT 1`,
    [tenantId],
  );
  return rows[0] ? mapSubscriptionRow(rows[0]) : null;
};

export const findSubscriptionByEasypayId = async (easypayId: string): Promise<TenantSubscription | null> => {
  const [rows] = await pool.query<SubscriptionRow[]>(
    'SELECT * FROM tenant_subscriptions WHERE easypay_subscription_id = ? LIMIT 1',
    [easypayId],
  );
  return rows[0] ? mapSubscriptionRow(rows[0]) : null;
};

export const listTenantSubscriptions = async (tenantId: number): Promise<TenantSubscription[]> => {
  const [rows] = await pool.query<SubscriptionRow[]>(
    'SELECT * FROM tenant_subscriptions WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 50',
    [tenantId],
  );
  return rows.map(mapSubscriptionRow);
};

export const updateSubscription = async (
  id: number,
  updates: UpdateSubscriptionInput,
): Promise<TenantSubscription | null> => {
  const fields: string[] = [];
  const params: unknown[] = [];

  if (updates.easypaySubscriptionId !== undefined) {
    fields.push('easypay_subscription_id = ?');
    params.push(updates.easypaySubscriptionId);
  }
  if (updates.easypayFrequentId !== undefined) {
    fields.push('easypay_frequent_id = ?');
    params.push(updates.easypayFrequentId);
  }
  if (updates.status !== undefined) {
    fields.push('status = ?');
    params.push(updates.status);
  }
  if (updates.nextBillingAt !== undefined) {
    fields.push('next_billing_at = ?');
    params.push(updates.nextBillingAt);
  }
  if (updates.startedAt !== undefined) {
    fields.push('started_at = ?');
    params.push(updates.startedAt);
  }
  if (updates.cancelledAt !== undefined) {
    fields.push('cancelled_at = ?');
    params.push(updates.cancelledAt);
  }
  if (updates.expiresAt !== undefined) {
    fields.push('expires_at = ?');
    params.push(updates.expiresAt);
  }
  if (updates.sddMandateId !== undefined) {
    fields.push('sdd_mandate_id = ?');
    params.push(updates.sddMandateId);
  }
  if (updates.metadata !== undefined) {
    fields.push('metadata = ?');
    params.push(stringifyJson(updates.metadata));
  }

  if (!fields.length) {
    return findSubscriptionById(id);
  }

  fields.push('updated_at = NOW()');

  const [result] = await pool.query<ResultSetHeader>(
    `UPDATE tenant_subscriptions SET ${fields.join(', ')} WHERE id = ?`,
    [...params, id],
  );

  if (result.affectedRows === 0) {
    return null;
  }

  return findSubscriptionById(id);
};

// ─────────────────────────────────────────────────────────────────────────────
// Charge CRUD
// ─────────────────────────────────────────────────────────────────────────────

export const createCharge = async (input: CreateChargeInput): Promise<SubscriptionCharge> => {
  const [result] = await pool.query<ResultSetHeader>(
    `INSERT INTO subscription_charges
      (subscription_id, tenant_id, payment_method, currency, amount_cents,
       easypay_transaction_id, mb_entity, mb_reference, mb_expires_at, metadata)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
    [
      input.subscriptionId,
      input.tenantId,
      input.paymentMethod,
      input.currency,
      input.amountCents,
      input.easypayTransactionId ?? null,
      input.mbEntity ?? null,
      input.mbReference ?? null,
      input.mbExpiresAt ?? null,
      stringifyJson(input.metadata),
    ],
  );

  return (await findChargeById(result.insertId))!;
};

export const findChargeById = async (id: number): Promise<SubscriptionCharge | null> => {
  const [rows] = await pool.query<ChargeRow[]>(
    'SELECT * FROM subscription_charges WHERE id = ? LIMIT 1',
    [id],
  );
  return rows[0] ? mapChargeRow(rows[0]) : null;
};

export const findChargeByEasypayTransactionId = async (txId: string): Promise<SubscriptionCharge | null> => {
  const [rows] = await pool.query<ChargeRow[]>(
    'SELECT * FROM subscription_charges WHERE easypay_transaction_id = ? LIMIT 1',
    [txId],
  );
  return rows[0] ? mapChargeRow(rows[0]) : null;
};

export const listSubscriptionCharges = async (subscriptionId: number): Promise<SubscriptionCharge[]> => {
  const [rows] = await pool.query<ChargeRow[]>(
    'SELECT * FROM subscription_charges WHERE subscription_id = ? ORDER BY created_at DESC LIMIT 100',
    [subscriptionId],
  );
  return rows.map(mapChargeRow);
};

export const updateCharge = async (
  id: number,
  updates: UpdateChargeInput,
): Promise<SubscriptionCharge | null> => {
  const fields: string[] = [];
  const params: unknown[] = [];

  if (updates.easypayTransactionId !== undefined) {
    fields.push('easypay_transaction_id = ?');
    params.push(updates.easypayTransactionId);
  }
  if (updates.easypayCaptureId !== undefined) {
    fields.push('easypay_capture_id = ?');
    params.push(updates.easypayCaptureId);
  }
  if (updates.status !== undefined) {
    fields.push('status = ?');
    params.push(updates.status);
  }
  if (updates.paidAt !== undefined) {
    fields.push('paid_at = ?');
    params.push(updates.paidAt);
  }
  if (updates.failedAt !== undefined) {
    fields.push('failed_at = ?');
    params.push(updates.failedAt);
  }
  if (updates.failureReason !== undefined) {
    fields.push('failure_reason = ?');
    params.push(updates.failureReason);
  }
  if (updates.mbEntity !== undefined) {
    fields.push('mb_entity = ?');
    params.push(updates.mbEntity);
  }
  if (updates.mbReference !== undefined) {
    fields.push('mb_reference = ?');
    params.push(updates.mbReference);
  }
  if (updates.mbExpiresAt !== undefined) {
    fields.push('mb_expires_at = ?');
    params.push(updates.mbExpiresAt);
  }
  if (updates.metadata !== undefined) {
    fields.push('metadata = ?');
    params.push(stringifyJson(updates.metadata));
  }

  if (!fields.length) {
    return findChargeById(id);
  }

  fields.push('updated_at = NOW()');

  const [result] = await pool.query<ResultSetHeader>(
    `UPDATE subscription_charges SET ${fields.join(', ')} WHERE id = ?`,
    [...params, id],
  );

  if (result.affectedRows === 0) {
    return null;
  }

  return findChargeById(id);
};

// ─────────────────────────────────────────────────────────────────────────────
// Webhook Logging
// ─────────────────────────────────────────────────────────────────────────────

export const logWebhook = async (
  eventType: string,
  easypayId: string | null,
  payload: unknown,
): Promise<EasypayWebhookLog> => {
  const [result] = await pool.query<ResultSetHeader>(
    `INSERT INTO easypay_webhooks (event_type, easypay_id, payload)
     VALUES (?, ?, ?)`,
    [eventType, easypayId, typeof payload === 'string' ? payload : JSON.stringify(payload)],
  );

  const [rows] = await pool.query<WebhookRow[]>(
    'SELECT * FROM easypay_webhooks WHERE id = ? LIMIT 1',
    [result.insertId],
  );

  return mapWebhookRow(rows[0]);
};

export const markWebhookProcessed = async (id: number, errorMessage?: string): Promise<void> => {
  await pool.query(
    `UPDATE easypay_webhooks
     SET processed_at = NOW(), error_message = ?
     WHERE id = ?`,
    [errorMessage ?? null, id],
  );
};
