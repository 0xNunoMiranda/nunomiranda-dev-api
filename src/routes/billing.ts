import { Router } from 'express';
import { z } from 'zod';
import { createBadRequest, createNotFound } from '../errors';
import logger from '../logger';
import requireAdminSecret from '../middleware/adminSecret';
import { success } from '../responses';
import {
  createCharge,
  createSubscription,
  findChargeByEasypayTransactionId,
  findSubscriptionByEasypayId,
  findSubscriptionById,
  findSubscriptionByTenantId,
  listSubscriptionCharges,
  listTenantSubscriptions,
  logWebhook,
  markWebhookProcessed,
  updateCharge,
  updateSubscription,
  type PaymentMethod,
  type TenantSubscription,
  type SubscriptionCharge,
} from '../services/billingService';
import easypayService, { EasypayService } from '../services/easypayService';
import { findSubscriptionPlanById } from '../services/subscriptionPlanService';

const router = Router();

// ─────────────────────────────────────────────────────────────────────────────
// Serializers
// ─────────────────────────────────────────────────────────────────────────────

const serializeSubscription = (sub: TenantSubscription) => ({
  id: sub.id,
  tenantId: sub.tenantId,
  planId: sub.planId,
  easypaySubscriptionId: sub.easypaySubscriptionId,
  status: sub.status,
  paymentMethod: sub.paymentMethod,
  billingPeriod: sub.billingPeriod,
  currency: sub.currency,
  amountCents: sub.amountCents,
  nextBillingAt: sub.nextBillingAt,
  trialEndsAt: sub.trialEndsAt,
  startedAt: sub.startedAt,
  cancelledAt: sub.cancelledAt,
  expiresAt: sub.expiresAt,
  customerName: sub.customerName,
  customerEmail: sub.customerEmail,
  createdAt: sub.createdAt,
  updatedAt: sub.updatedAt,
});

const serializeCharge = (charge: SubscriptionCharge) => ({
  id: charge.id,
  subscriptionId: charge.subscriptionId,
  easypayTransactionId: charge.easypayTransactionId,
  status: charge.status,
  paymentMethod: charge.paymentMethod,
  currency: charge.currency,
  amountCents: charge.amountCents,
  paidAt: charge.paidAt,
  failedAt: charge.failedAt,
  failureReason: charge.failureReason,
  mbEntity: charge.mbEntity,
  mbReference: charge.mbReference,
  mbExpiresAt: charge.mbExpiresAt,
  createdAt: charge.createdAt,
});

// ─────────────────────────────────────────────────────────────────────────────
// Validation Schemas
// ─────────────────────────────────────────────────────────────────────────────

const createSubscriptionSchema = z.object({
  tenantId: z.number().int().positive(),
  planId: z.number().int().positive(),
  paymentMethod: z.enum(['cc', 'dd', 'mbway', 'multibanco', 'credit_card', 'direct_debit']),
  customer: z.object({
    name: z.string().min(2).max(160),
    email: z.string().email(),
    phone: z.string().min(9).max(32).optional(),
    fiscalNumber: z.string().max(32).optional(),
  }),
  sddMandate: z.object({
    iban: z.string().min(15).max(64),
    accountHolder: z.string().min(2).max(160),
  }).optional(),
});

// ─────────────────────────────────────────────────────────────────────────────
// Admin Routes (protected by adminSecret)
// ─────────────────────────────────────────────────────────────────────────────

router.use('/admin', requireAdminSecret);

// List subscriptions for a tenant
router.get('/admin/tenants/:tenantId/subscriptions', async (req, res, next) => {
  try {
    const tenantId = Number(req.params.tenantId);
    if (Number.isNaN(tenantId)) {
      throw createBadRequest('Invalid tenantId');
    }
    const subscriptions = await listTenantSubscriptions(tenantId);
    return res.json(success({ subscriptions: subscriptions.map(serializeSubscription) }));
  } catch (error) {
    return next(error);
  }
});

// Get subscription by ID
router.get('/admin/subscriptions/:id', async (req, res, next) => {
  try {
    const id = Number(req.params.id);
    if (Number.isNaN(id)) {
      throw createBadRequest('Invalid subscription id');
    }
    const subscription = await findSubscriptionById(id);
    if (!subscription) {
      throw createNotFound('Subscription not found');
    }
    const charges = await listSubscriptionCharges(id);
    return res.json(success({
      subscription: serializeSubscription(subscription),
      charges: charges.map(serializeCharge),
    }));
  } catch (error) {
    return next(error);
  }
});

// Cancel subscription (admin)
router.post('/admin/subscriptions/:id/cancel', async (req, res, next) => {
  try {
    const id = Number(req.params.id);
    if (Number.isNaN(id)) {
      throw createBadRequest('Invalid subscription id');
    }

    const subscription = await findSubscriptionById(id);
    if (!subscription) {
      throw createNotFound('Subscription not found');
    }

    // Cancel on Easypay if exists
    if (subscription.easypaySubscriptionId) {
      const result = await easypayService.deleteSubscription(subscription.easypaySubscriptionId);
      if (!result.ok) {
        logger.warn({ subscriptionId: id, error: result.error }, 'Failed to cancel Easypay subscription');
      }
    }

    const updated = await updateSubscription(id, {
      status: 'cancelled',
      cancelledAt: new Date(),
    });

    return res.json(success({ subscription: updated ? serializeSubscription(updated) : null }));
  } catch (error) {
    return next(error);
  }
});

// ─────────────────────────────────────────────────────────────────────────────
// Public Billing Routes (for consumer/PHP side)
// ─────────────────────────────────────────────────────────────────────────────

// Create a new subscription
router.post('/subscriptions', async (req, res, next) => {
  try {
    const body = createSubscriptionSchema.parse(req.body);

    // Get the plan
    const plan = await findSubscriptionPlanById(body.planId);
    if (!plan || !plan.isActive || plan.archivedAt) {
      throw createBadRequest('Plan not found or inactive');
    }

    // Check if tenant already has active subscription
    const existing = await findSubscriptionByTenantId(body.tenantId);
    if (existing && existing.status === 'active') {
      throw createBadRequest('Tenant already has an active subscription');
    }

    // Normalize payment method (credit_card -> cc, direct_debit -> dd)
    let paymentMethod = body.paymentMethod as PaymentMethod;
    if (body.paymentMethod === 'credit_card') {
      paymentMethod = 'cc';
    } else if (body.paymentMethod === 'direct_debit') {
      paymentMethod = 'dd';
    }

    // Extract customer data
    const customerName = body.customer.name;
    const customerEmail = body.customer.email;
    const customerPhone = body.customer.phone;
    const customerFiscalNumber = body.customer.fiscalNumber;
    const sddIban = body.sddMandate?.iban;
    const sddAccountHolder = body.sddMandate?.accountHolder;

    // Create local subscription record
    const subscription = await createSubscription({
      tenantId: body.tenantId,
      planId: body.planId,
      paymentMethod,
      billingPeriod: plan.billingPeriod,
      currency: plan.currency,
      amountCents: plan.priceCents,
      trialDays: plan.trialDays,
      customerName,
      customerEmail,
      customerPhone,
      customerFiscalNumber,
      sddIban,
    });

    // Determine flow based on payment method
    let easypayResponse: unknown = null;
    let checkoutUrl: string | null = null;
    let mbEntity: string | null = null;
    let mbReference: string | null = null;

    const amountEuros = plan.priceCents / 100;
    const startTime = new Date();
    if (plan.trialDays > 0) {
      startTime.setDate(startTime.getDate() + plan.trialDays);
    }

    // For CC and DD we can use native Easypay subscriptions (recurring)
    // For MB Way and Multibanco we use Frequent or Single payments
    if (paymentMethod === 'cc' || paymentMethod === 'dd') {
      // Create Easypay subscription (native recurring)
      const easypayInput = {
        value: amountEuros,
        frequency: EasypayService.mapBillingPeriodToFrequency(plan.billingPeriod),
        method: paymentMethod === 'cc' ? 'cc' : 'dd' as 'cc' | 'dd',
        start_time: EasypayService.formatDate(startTime),
        unlimited_payments: true,
        capture_now: plan.trialDays === 0,
        retries: 3,
        customer: {
          name: customerName,
          email: customerEmail,
          phone: customerPhone,
          fiscal_number: customerFiscalNumber,
          key: `tenant-${body.tenantId}`,
        },
        key: `sub-${subscription.id}`,
        ...(paymentMethod === 'dd' && sddIban
          ? {
              sdd_mandate: {
                iban: sddIban,
                name: customerName,
                email: customerEmail,
                phone: customerPhone,
                account_holder: sddAccountHolder || customerName,
                key: `mandate-${subscription.id}`,
              },
            }
          : {}),
      };

      const result = await easypayService.createSubscription(easypayInput);

      if (!result.ok) {
        await updateSubscription(subscription.id, { status: 'failed' });
        throw createBadRequest(`Easypay error: ${result.error}`);
      }

      easypayResponse = result.data;
      checkoutUrl = result.data.method?.url ?? null;

      await updateSubscription(subscription.id, {
        easypaySubscriptionId: result.data.id,
        status: checkoutUrl ? 'pending' : 'active',
        startedAt: plan.trialDays === 0 ? new Date() : null,
        nextBillingAt: startTime,
      });
    } else if (paymentMethod === 'multibanco') {
      // Create single payment with Multibanco for first charge
      const result = await easypayService.createSinglePayment({
        value: amountEuros,
        method: 'mb',
        customer: {
          name: customerName,
          email: customerEmail,
          phone: customerPhone,
          key: `tenant-${body.tenantId}`,
        },
        key: `sub-${subscription.id}-initial`,
      });

      if (!result.ok) {
        await updateSubscription(subscription.id, { status: 'failed' });
        throw createBadRequest(`Easypay error: ${result.error}`);
      }

      easypayResponse = result.data;
      mbEntity = result.data.method?.entity ?? null;
      mbReference = result.data.method?.reference ?? null;

      // Create charge record
      await createCharge({
        subscriptionId: subscription.id,
        tenantId: body.tenantId,
        paymentMethod: 'multibanco',
        currency: plan.currency,
        amountCents: plan.priceCents,
        easypayTransactionId: result.data.id,
        mbEntity,
        mbReference,
      });

      await updateSubscription(subscription.id, {
        status: 'pending',
        metadata: { easypay_single_id: result.data.id },
      });
    } else if (paymentMethod === 'mbway') {
      // Create single payment with MB Way
      const result = await easypayService.createSinglePayment({
        value: amountEuros,
        method: 'mbw',
        customer: {
          name: customerName,
          email: customerEmail,
          phone: customerPhone,
          key: `tenant-${body.tenantId}`,
        },
        key: `sub-${subscription.id}-initial`,
      });

      if (!result.ok) {
        await updateSubscription(subscription.id, { status: 'failed' });
        throw createBadRequest(`Easypay error: ${result.error}`);
      }

      easypayResponse = result.data;

      await createCharge({
        subscriptionId: subscription.id,
        tenantId: body.tenantId,
        paymentMethod: 'mbway',
        currency: plan.currency,
        amountCents: plan.priceCents,
        easypayTransactionId: result.data.id,
      });

      await updateSubscription(subscription.id, {
        status: 'pending',
        metadata: { easypay_single_id: result.data.id },
      });
    } else {
      // Google Pay / Apple Pay - would need frontend integration
      // For now, return pending and let frontend complete via Checkout SDK
      await updateSubscription(subscription.id, { status: 'pending' });
    }

    const updatedSubscription = await findSubscriptionById(subscription.id);

    // Build response with payment method info
    const responseMethod: Record<string, unknown> = {};
    if (checkoutUrl) {
      responseMethod.url = checkoutUrl;
    }
    if (mbEntity && mbReference) {
      responseMethod.entity = mbEntity;
      responseMethod.reference = mbReference;
      responseMethod.value = amountEuros;
    }
    if (paymentMethod === 'mbway' && customerPhone) {
      responseMethod.alias = customerPhone;
    }

    return res.status(201).json(success({
      subscriptionId: subscription.id,
      subscription: updatedSubscription ? serializeSubscription(updatedSubscription) : null,
      status: updatedSubscription?.status ?? 'pending',
      checkoutUrl,
      mbEntity,
      mbReference,
      method: Object.keys(responseMethod).length > 0 ? responseMethod : null,
      easypayResponse: process.env.NODE_ENV !== 'production' ? easypayResponse : undefined,
    }));
  } catch (error) {
    if (error instanceof z.ZodError) {
      return next(createBadRequest(error.errors[0]?.message || 'Invalid payload'));
    }
    return next(error);
  }
});

// Get current subscription for a tenant
router.get('/subscriptions/tenant/:tenantId', async (req, res, next) => {
  try {
    const tenantId = Number(req.params.tenantId);
    if (Number.isNaN(tenantId)) {
      throw createBadRequest('Invalid tenantId');
    }

    const subscription = await findSubscriptionByTenantId(tenantId);
    if (!subscription) {
      return res.json(success({ subscription: null }));
    }

    const charges = await listSubscriptionCharges(subscription.id);
    return res.json(success({
      subscription: serializeSubscription(subscription),
      charges: charges.slice(0, 10).map(serializeCharge),
    }));
  } catch (error) {
    return next(error);
  }
});

// Cancel subscription (public)
router.post('/subscriptions/:id/cancel', async (req, res, next) => {
  try {
    const id = Number(req.params.id);
    if (Number.isNaN(id)) {
      throw createBadRequest('Invalid subscription id');
    }

    const subscription = await findSubscriptionById(id);
    if (!subscription) {
      throw createNotFound('Subscription not found');
    }

    if (subscription.easypaySubscriptionId) {
      await easypayService.deleteSubscription(subscription.easypaySubscriptionId);
    }

    const updated = await updateSubscription(id, {
      status: 'cancelled',
      cancelledAt: new Date(),
    });

    return res.json(success({ subscription: updated ? serializeSubscription(updated) : null }));
  } catch (error) {
    return next(error);
  }
});

// ─────────────────────────────────────────────────────────────────────────────
// Easypay Webhooks
// ─────────────────────────────────────────────────────────────────────────────

router.post('/webhooks/easypay', async (req, res, next) => {
  try {
    const payload = req.body;
    const eventType = payload.type ?? payload.event ?? 'unknown';
    const easypayId = payload.id ?? payload.subscription_id ?? payload.transaction_id ?? null;

    logger.info({ eventType, easypayId }, 'Easypay webhook received');

    // Log webhook
    const webhookLog = await logWebhook(eventType, easypayId, payload);

    try {
      await processEasypayWebhook(payload);
      await markWebhookProcessed(webhookLog.id);
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : 'Unknown error';
      await markWebhookProcessed(webhookLog.id, errorMessage);
      logger.error({ webhookId: webhookLog.id, error: errorMessage }, 'Failed to process webhook');
    }

    // Always return 200 to Easypay
    return res.json({ ok: true });
  } catch (error) {
    logger.error({ error }, 'Webhook handler error');
    return res.status(200).json({ ok: true });
  }
});

async function processEasypayWebhook(payload: Record<string, unknown>): Promise<void> {
  const eventType = payload.type ?? payload.event;
  const status = payload.status as string | undefined;
  const transactionId = payload.id as string | undefined;
  const subscriptionId = payload.subscription_id as string | undefined;

  // Handle subscription events
  if (subscriptionId) {
    const subscription = await findSubscriptionByEasypayId(subscriptionId);
    if (subscription) {
      if (status === 'active') {
        await updateSubscription(subscription.id, {
          status: 'active',
          startedAt: new Date(),
        });
      } else if (status === 'inactive' || status === 'deleted') {
        await updateSubscription(subscription.id, {
          status: 'cancelled',
          cancelledAt: new Date(),
        });
      }
    }
  }

  // Handle payment/capture events
  if (transactionId) {
    const charge = await findChargeByEasypayTransactionId(transactionId);
    if (charge) {
      if (status === 'success' || status === 'paid' || eventType === 'capture') {
        await updateCharge(charge.id, {
          status: 'paid',
          paidAt: new Date(),
        });

        // Activate subscription if this was the first payment
        const subscription = await findSubscriptionById(charge.subscriptionId);
        if (subscription && subscription.status === 'pending') {
          await updateSubscription(subscription.id, {
            status: 'active',
            startedAt: new Date(),
          });
        }
      } else if (status === 'failed' || status === 'error' || status === 'declined') {
        await updateCharge(charge.id, {
          status: 'failed',
          failedAt: new Date(),
          failureReason: (payload.message as string) ?? 'Payment failed',
        });
      }
    }
  }
}

// Test Easypay connection
router.get('/easypay/ping', async (_req, res, next) => {
  try {
    const result = await easypayService.ping();
    return res.json(success({ easypay: result }));
  } catch (error) {
    return next(error);
  }
});

export default router;
