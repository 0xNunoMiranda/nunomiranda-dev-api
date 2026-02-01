import type { SubscriptionPlan } from '../services/subscriptionPlanService';

export const serializeSubscriptionPlan = (plan: SubscriptionPlan) => ({
  id: plan.id,
  name: plan.name,
  slug: plan.slug,
  description: plan.description,
  billingPeriod: plan.billingPeriod,
  currency: plan.currency,
  priceCents: plan.priceCents,
  setupFeeCents: plan.setupFeeCents,
  trialDays: plan.trialDays,
  modules: plan.modules,
  features: plan.features,
  metadata: plan.metadata,
  sortOrder: plan.sortOrder,
  isActive: plan.isActive,
  archivedAt: plan.archivedAt,
  createdAt: plan.createdAt,
  updatedAt: plan.updatedAt,
});
