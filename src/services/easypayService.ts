import config from '../config';
import logger from '../logger';

export type EasypayMethod = 'cc' | 'dd' | 'mbw' | 'mb' | 'ap' | 'gp';

export type EasypayFrequency = '1D' | '1W' | '2W' | '1M' | '2M' | '3M' | '4M' | '6M' | '1Y' | '2Y' | '3Y';

export interface EasypayCustomer {
  name?: string;
  email?: string;
  phone?: string;
  phone_indicative?: string;
  fiscal_number?: string;
  key?: string;
  language?: 'PT' | 'EN' | 'ES' | 'FR' | 'DE' | 'IT';
}

export interface EasypaySddMandate {
  iban: string;
  name: string;
  email?: string;
  phone?: string;
  account_holder: string;
  key?: string;
  max_num_debits?: string;
}

export interface EasypayCapture {
  transaction_key?: string;
  descriptive?: string;
  account?: { id?: string };
}

export interface CreateSubscriptionInput {
  value: number;
  frequency: EasypayFrequency;
  method: 'cc' | 'dd';
  start_time: string;
  expiration_time?: string;
  max_captures?: number;
  unlimited_payments?: boolean;
  currency?: string;
  customer?: EasypayCustomer;
  key?: string;
  capture_now?: boolean;
  retries?: number;
  failover?: boolean;
  sdd_mandate?: EasypaySddMandate;
  capture?: EasypayCapture;
  frequent_id?: string;
}

export interface CreateSinglePaymentInput {
  value: number;
  method: EasypayMethod;
  currency?: string;
  customer?: EasypayCustomer;
  key?: string;
  capture?: EasypayCapture;
  expiration_time?: string;
  sdd_mandate?: EasypaySddMandate;
}

export interface CreateFrequentInput {
  value?: number;
  method: EasypayMethod;
  min_value?: number;
  max_value?: number;
  unlimited_payments?: boolean;
  currency?: string;
  customer?: EasypayCustomer;
  key?: string;
  expiration_time?: string;
  sdd_mandate?: EasypaySddMandate;
}

export interface EasypaySubscriptionResponse {
  id: string;
  status: string;
  key?: string;
  expiration_time?: string;
  start_time?: string;
  frequency?: EasypayFrequency;
  retries?: number;
  max_captures?: number;
  failover?: boolean;
  capture_now?: boolean;
  unlimited_payments?: boolean;
  customer?: EasypayCustomer & { id?: string };
  method?: {
    type: string;
    status: string;
    url?: string;
    last_four?: string;
    card_type?: string;
    expiration_date?: string;
    entity?: string;
    reference?: string;
  };
  transactions?: Array<{
    id: string;
    key?: string;
    status: string;
    value?: number;
  }>;
  currency?: string;
  value?: number;
  created_at?: string;
}

export interface EasypaySingleResponse {
  id: string;
  status: string;
  key?: string;
  method?: {
    type: string;
    status: string;
    url?: string;
    entity?: string;
    reference?: string;
    alias?: string;
  };
  customer?: EasypayCustomer & { id?: string };
  value?: number;
  currency?: string;
  expiration_time?: string;
  created_at?: string;
}

export interface EasypayFrequentResponse {
  id: string;
  status: string;
  key?: string;
  method?: {
    type: string;
    status: string;
    url?: string;
    entity?: string;
    reference?: string;
  };
  customer?: EasypayCustomer & { id?: string };
  created_at?: string;
}

export interface EasypayError {
  status: 'error';
  message: string | string[];
  code?: string | number;
}

type EasypayResult<T> =
  | { ok: true; data: T }
  | { ok: false; error: string; status?: number; raw?: unknown };

export class EasypayService {
  private readonly baseUrl: string;
  private readonly accountId: string;
  private readonly apiKey: string;

  constructor() {
    this.baseUrl = config.EASYPAY_API_BASE;
    this.accountId = config.EASYPAY_ACCOUNT_ID;
    this.apiKey = config.EASYPAY_API_KEY;
  }

  private async request<T>(
    method: 'GET' | 'POST' | 'PATCH' | 'DELETE',
    path: string,
    body?: unknown,
  ): Promise<EasypayResult<T>> {
    const url = `${this.baseUrl}${path}`;
    const headers: HeadersInit = {
      AccountId: this.accountId,
      ApiKey: this.apiKey,
      'Content-Type': 'application/json',
      Accept: 'application/json',
    };

    try {
      const response = await fetch(url, {
        method,
        headers,
        body: body ? JSON.stringify(body) : undefined,
      });

      const text = await response.text();
      let data: unknown;

      try {
        data = JSON.parse(text);
      } catch {
        logger.error({ url, status: response.status, text }, 'Easypay: invalid JSON response');
        return { ok: false, error: 'Invalid JSON response from Easypay', status: response.status };
      }

      if (!response.ok) {
        const errorData = data as EasypayError;
        const message = Array.isArray(errorData.message)
          ? errorData.message.join(', ')
          : errorData.message || 'Unknown Easypay error';
        logger.warn({ url, status: response.status, errorData }, 'Easypay API error');
        return { ok: false, error: message, status: response.status, raw: data };
      }

      logger.debug({ url, method, status: response.status }, 'Easypay request successful');
      return { ok: true, data: data as T };
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Unknown error';
      logger.error({ url, method, error: message }, 'Easypay request failed');
      return { ok: false, error: `Request failed: ${message}` };
    }
  }

  // ─────────────────────────────────────────────────────────────────────────────
  // Subscriptions (CC / DD only - recurring with fixed amount)
  // ─────────────────────────────────────────────────────────────────────────────

  async createSubscription(input: CreateSubscriptionInput): Promise<EasypayResult<EasypaySubscriptionResponse>> {
    return this.request<EasypaySubscriptionResponse>('POST', '/subscription', input);
  }

  async getSubscription(id: string): Promise<EasypayResult<EasypaySubscriptionResponse>> {
    return this.request<EasypaySubscriptionResponse>('GET', `/subscription/${id}`);
  }

  async updateSubscription(
    id: string,
    updates: Partial<CreateSubscriptionInput> & { status?: 'active' | 'inactive' },
  ): Promise<EasypayResult<{ status: string; message: EasypaySubscriptionResponse }>> {
    return this.request('PATCH', `/subscription/${id}`, updates);
  }

  async deleteSubscription(id: string): Promise<EasypayResult<void>> {
    return this.request('DELETE', `/subscription/${id}`);
  }

  // ─────────────────────────────────────────────────────────────────────────────
  // Single Payments (all methods - one-time)
  // ─────────────────────────────────────────────────────────────────────────────

  async createSinglePayment(input: CreateSinglePaymentInput): Promise<EasypayResult<EasypaySingleResponse>> {
    return this.request<EasypaySingleResponse>('POST', '/single', input);
  }

  async getSinglePayment(id: string): Promise<EasypayResult<EasypaySingleResponse>> {
    return this.request<EasypaySingleResponse>('GET', `/single/${id}`);
  }

  async deleteSinglePayment(id: string): Promise<EasypayResult<void>> {
    return this.request('DELETE', `/single/${id}`);
  }

  // ─────────────────────────────────────────────────────────────────────────────
  // Frequent Payments (tokenization for variable on-demand charges)
  // ─────────────────────────────────────────────────────────────────────────────

  async createFrequent(input: CreateFrequentInput): Promise<EasypayResult<EasypayFrequentResponse>> {
    return this.request<EasypayFrequentResponse>('POST', '/frequent', input);
  }

  async getFrequent(id: string): Promise<EasypayResult<EasypayFrequentResponse>> {
    return this.request<EasypayFrequentResponse>('GET', `/frequent/${id}`);
  }

  async captureFrequent(
    id: string,
    value: number,
    options?: { transaction_key?: string; descriptive?: string },
  ): Promise<EasypayResult<{ id: string; status: string; value: number }>> {
    return this.request('POST', `/capture/${id}`, { value, ...options });
  }

  async deleteFrequent(id: string): Promise<EasypayResult<void>> {
    return this.request('DELETE', `/frequent/${id}`);
  }

  // ─────────────────────────────────────────────────────────────────────────────
  // Captures & Refunds
  // ─────────────────────────────────────────────────────────────────────────────

  async getCapture(id: string): Promise<EasypayResult<unknown>> {
    return this.request('GET', `/capture/${id}`);
  }

  async refund(
    transactionId: string,
    value?: number,
    iban?: string,
  ): Promise<EasypayResult<{ id: string; status: string }>> {
    const body: { value?: number; iban?: string } = {};
    if (value !== undefined) body.value = value;
    if (iban) body.iban = iban;
    return this.request('POST', `/refund/${transactionId}`, body);
  }

  // ─────────────────────────────────────────────────────────────────────────────
  // Utilities
  // ─────────────────────────────────────────────────────────────────────────────

  async ping(): Promise<EasypayResult<{ message: string }>> {
    return this.request<{ message: string }>('GET', '/system/ping');
  }

  /**
   * Maps our internal payment method to Easypay's method code
   */
  static mapPaymentMethod(method: string): EasypayMethod {
    const mapping: Record<string, EasypayMethod> = {
      cc: 'cc',
      credit_card: 'cc',
      dd: 'dd',
      direct_debit: 'dd',
      mbway: 'mbw',
      mb_way: 'mbw',
      multibanco: 'mb',
      google_pay: 'gp',
      apple_pay: 'ap',
    };
    return mapping[method] ?? 'cc';
  }

  /**
   * Maps billing period to Easypay frequency
   */
  static mapBillingPeriodToFrequency(period: 'monthly' | 'annual'): EasypayFrequency {
    return period === 'annual' ? '1Y' : '1M';
  }

  /**
   * Format date for Easypay (Y-m-d H:i)
   */
  static formatDate(date: Date): string {
    const pad = (n: number) => n.toString().padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
  }
}

export const easypayService = new EasypayService();
export default easypayService;
