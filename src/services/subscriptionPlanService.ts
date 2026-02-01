import { ResultSetHeader, RowDataPacket } from 'mysql2';
import pool from '../db';

export type BillingPeriod = 'monthly' | 'annual';

export type PlanModule = {
  code: string;
  name: string;
  description?: string;
  priceCents?: number;
  limits?: Record<string, unknown>;
  included?: boolean;
};

export type SubscriptionPlan = {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  billingPeriod: BillingPeriod;
  currency: string;
  priceCents: number;
  setupFeeCents: number;
  trialDays: number;
  modules: PlanModule[];
  features: string[];
  metadata: Record<string, unknown> | null;
  sortOrder: number;
  isActive: boolean;
  archivedAt: string | null;
  createdAt: string;
  updatedAt: string;
};

interface SubscriptionPlanRow extends RowDataPacket {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  billing_period: BillingPeriod;
  currency: string;
  price_cents: number;
  setup_fee_cents: number;
  trial_days: number;
  modules_json: string | null;
  features_json: string | null;
  metadata: string | null;
  sort_order: number;
  is_active: number;
  archived_at: Date | null;
  created_at: Date;
  updated_at: Date;
}

export type CreateSubscriptionPlanInput = {
  name: string;
  slug: string;
  description?: string | null;
  billingPeriod: BillingPeriod;
  currency: string;
  priceCents: number;
  setupFeeCents?: number;
  trialDays?: number;
  modules?: PlanModule[] | null;
  features?: string[] | null;
  metadata?: Record<string, unknown> | null;
  sortOrder?: number;
  isActive?: boolean;
};

export type UpdateSubscriptionPlanInput = Partial<CreateSubscriptionPlanInput>;

export type ListSubscriptionPlansOptions = {
  includeInactive?: boolean;
  includeArchived?: boolean;
  billingPeriod?: BillingPeriod;
};

const parseJson = <T>(value: string | null): T | null => {
  if (!value) return null;
  try {
    return JSON.parse(value) as T;
  } catch (_error) {
    return null;
  }
};

const stringifyJson = (value?: unknown | null) => {
  if (value === undefined || value === null) {
    return null;
  }
  return JSON.stringify(value);
};

const mapPlanRow = (row: SubscriptionPlanRow): SubscriptionPlan => ({
  id: row.id,
  name: row.name,
  slug: row.slug,
  description: row.description,
  billingPeriod: row.billing_period,
  currency: row.currency,
  priceCents: row.price_cents,
  setupFeeCents: row.setup_fee_cents,
  trialDays: row.trial_days,
  modules: parseJson<PlanModule[]>(row.modules_json) ?? [],
  features: parseJson<string[]>(row.features_json) ?? [],
  metadata: parseJson<Record<string, unknown>>(row.metadata),
  sortOrder: row.sort_order,
  isActive: row.is_active === 1,
  archivedAt: row.archived_at ? row.archived_at.toISOString() : null,
  createdAt: row.created_at.toISOString(),
  updatedAt: row.updated_at.toISOString(),
});

export const listSubscriptionPlans = async (
  options: ListSubscriptionPlansOptions = {},
): Promise<SubscriptionPlan[]> => {
  const clauses: string[] = [];
  const params: unknown[] = [];

  if (!options.includeInactive) {
    clauses.push('is_active = 1');
  }
  if (!options.includeArchived) {
    clauses.push('archived_at IS NULL');
  }
  if (options.billingPeriod) {
    clauses.push('billing_period = ?');
    params.push(options.billingPeriod);
  }

  const where = clauses.length ? `WHERE ${clauses.join(' AND ')}` : '';
  const [rows] = await pool.query<SubscriptionPlanRow[]>(
    `SELECT * FROM subscription_plans ${where} ORDER BY sort_order DESC, price_cents ASC, id DESC`,
    params,
  );
  return rows.map(mapPlanRow);
};

export const findSubscriptionPlanById = async (planId: number): Promise<SubscriptionPlan | null> => {
  const [rows] = await pool.query<SubscriptionPlanRow[]>(
    'SELECT * FROM subscription_plans WHERE id = ? LIMIT 1',
    [planId],
  );
  const row = rows[0];
  return row ? mapPlanRow(row) : null;
};

export const createSubscriptionPlan = async (
  input: CreateSubscriptionPlanInput,
): Promise<SubscriptionPlan> => {
  const [result] = await pool.query<ResultSetHeader>(
    `INSERT INTO subscription_plans
      (name, slug, description, billing_period, currency, price_cents, setup_fee_cents, trial_days,
       modules_json, features_json, metadata, sort_order, is_active)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
    [
      input.name,
      input.slug,
      input.description ?? null,
      input.billingPeriod,
      input.currency,
      input.priceCents,
      input.setupFeeCents ?? 0,
      input.trialDays ?? 0,
      stringifyJson(input.modules),
      stringifyJson(input.features),
      stringifyJson(input.metadata),
      input.sortOrder ?? 0,
      input.isActive ?? true,
    ],
  );

  return (await findSubscriptionPlanById(result.insertId))!;
};

export const updateSubscriptionPlan = async (
  planId: number,
  updates: UpdateSubscriptionPlanInput,
): Promise<SubscriptionPlan | null> => {
  const fields: string[] = [];
  const params: unknown[] = [];

  if (updates.name !== undefined) {
    fields.push('name = ?');
    params.push(updates.name);
  }
  if (updates.slug !== undefined) {
    fields.push('slug = ?');
    params.push(updates.slug);
  }
  if (updates.description !== undefined) {
    fields.push('description = ?');
    params.push(updates.description);
  }
  if (updates.billingPeriod !== undefined) {
    fields.push('billing_period = ?');
    params.push(updates.billingPeriod);
  }
  if (updates.currency !== undefined) {
    fields.push('currency = ?');
    params.push(updates.currency);
  }
  if (updates.priceCents !== undefined) {
    fields.push('price_cents = ?');
    params.push(updates.priceCents);
  }
  if (updates.setupFeeCents !== undefined) {
    fields.push('setup_fee_cents = ?');
    params.push(updates.setupFeeCents);
  }
  if (updates.trialDays !== undefined) {
    fields.push('trial_days = ?');
    params.push(updates.trialDays);
  }
  if (updates.modules !== undefined) {
    fields.push('modules_json = ?');
    params.push(stringifyJson(updates.modules));
  }
  if (updates.features !== undefined) {
    fields.push('features_json = ?');
    params.push(stringifyJson(updates.features));
  }
  if (updates.metadata !== undefined) {
    fields.push('metadata = ?');
    params.push(stringifyJson(updates.metadata));
  }
  if (updates.sortOrder !== undefined) {
    fields.push('sort_order = ?');
    params.push(updates.sortOrder);
  }
  if (updates.isActive !== undefined) {
    fields.push('is_active = ?');
    params.push(updates.isActive ? 1 : 0);
  }

  if (!fields.length) {
    return findSubscriptionPlanById(planId);
  }

  fields.push('updated_at = NOW()');

  const [result] = await pool.query<ResultSetHeader>(
    `UPDATE subscription_plans
     SET ${fields.join(', ')}
     WHERE id = ?`,
    [...params, planId],
  );

  if (result.affectedRows === 0) {
    return null;
  }

  return findSubscriptionPlanById(planId);
};

export const archiveSubscriptionPlan = async (planId: number): Promise<SubscriptionPlan | null> => {
  const [result] = await pool.query<ResultSetHeader>(
    `UPDATE subscription_plans
     SET archived_at = NOW(), is_active = 0, updated_at = NOW()
     WHERE id = ? AND archived_at IS NULL`,
    [planId],
  );

  if (result.affectedRows === 0) {
    return null;
  }

  return findSubscriptionPlanById(planId);
};
