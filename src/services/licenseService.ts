import { ResultSetHeader, RowDataPacket } from 'mysql2';
import pool from '../db';
import crypto from 'crypto';

export interface License {
    id: number;
    tenant_id: number;
    license_key: string;
    client_name: string;
    client_email?: string;
    modules: ModulesConfig;
    ai_messages_limit: number;
    ai_messages_used: number;
    email_limit: number;
    emails_sent: number;
    sms_limit: number;
    sms_sent: number;
    whatsapp_limit: number;
    whatsapp_sent: number;
    ai_calls_limit: number;
    ai_calls_used: number;
    status: 'active' | 'suspended' | 'expired' | 'trial' | 'revoked';
    trial_ends_at?: Date;
    created_at?: Date;
    updated_at?: Date;
}

export interface ModulesConfig {
    static_site?: {
        enabled: boolean;
        ai_generated: boolean;
        theme?: string;
    };
    bot_widget?: {
        enabled: boolean;
        type: 'faq' | 'ai' | 'hybrid';
        features: ('info' | 'bookings' | 'shop')[];
        exportable: boolean;
        position: 'floating' | 'tab' | 'both';
    };
    bot_whatsapp?: {
        enabled: boolean;
        features: ('info' | 'bookings' | 'shop')[];
        phone_number?: string;
    };
    ai_calls?: {
        enabled: boolean;
    };
    email?: {
        enabled: boolean;
        provider?: string;
    };
    sms?: {
        enabled: boolean;
        provider?: string;
    };
    shop?: {
        enabled: boolean;
        platform: 'prestashop' | 'woocommerce' | 'custom';
        url?: string;
    };
}

interface LicenseRow extends RowDataPacket {
    id: number;
    tenant_id: number;
    license_key: string;
    client_name: string;
    client_email: string | null;
    modules: string;
    ai_messages_limit: number;
    ai_messages_used: number;
    email_limit: number;
    emails_sent: number;
    sms_limit: number;
    sms_sent: number;
    whatsapp_limit: number;
    whatsapp_sent: number;
    ai_calls_limit: number;
    ai_calls_used: number;
    status: 'active' | 'suspended' | 'expired' | 'trial' | 'revoked';
    trial_ends_at: Date | null;
    billing_cycle_start: Date | null;
    billing_cycle_end: Date | null;
    created_at: Date;
    updated_at: Date;
}

interface UsageLogRow extends RowDataPacket {
    id: number;
    license_id: number;
    usage_type: string;
    tokens_used: number;
    cost_cents: number;
    metadata: string | null;
    created_at: Date;
}

export interface UsageType {
    type: 'ai_message' | 'email' | 'sms' | 'whatsapp' | 'ai_call' | 'ai_generation';
    limitField: string;
    usedField: string;
}

const USAGE_TYPES: Record<string, UsageType> = {
    ai_message: { type: 'ai_message', limitField: 'ai_messages_limit', usedField: 'ai_messages_used' },
    email: { type: 'email', limitField: 'email_limit', usedField: 'emails_sent' },
    sms: { type: 'sms', limitField: 'sms_limit', usedField: 'sms_sent' },
    whatsapp: { type: 'whatsapp', limitField: 'whatsapp_limit', usedField: 'whatsapp_sent' },
    ai_call: { type: 'ai_call', limitField: 'ai_calls_limit', usedField: 'ai_calls_used' },
    ai_generation: { type: 'ai_generation', limitField: 'ai_messages_limit', usedField: 'ai_messages_used' },
};

function parseLicenseRow(row: LicenseRow): License {
    return {
        ...row,
        client_email: row.client_email || undefined,
        modules: typeof row.modules === 'string' ? JSON.parse(row.modules) : row.modules,
        trial_ends_at: row.trial_ends_at || undefined,
    };
}

export class LicenseService {
    /**
     * Gera uma nova license key no formato: ntk_[12 hex].[48 hex]
     * Exemplo: ntk_bea3832cfabc.80d95c26104852ece8c90315ab0c324f9b02c1850cfca9f0
     */
    static generateLicenseKey(): string {
        const prefix = crypto.randomBytes(6).toString('hex');   // 12 chars
        const secret = crypto.randomBytes(24).toString('hex');  // 48 chars
        return `ntk_${prefix}.${secret}`;
    }

    /**
     * Cria uma nova licença para um tenant (revoga todas as anteriores)
     */
    static async createLicense(
        tenantId: number,
        clientName: string,
        clientEmail?: string,
        modules?: Partial<ModulesConfig>,
        limits?: Partial<{
            ai_messages_limit: number;
            email_limit: number;
            sms_limit: number;
            whatsapp_limit: number;
            ai_calls_limit: number;
        }>
    ): Promise<License> {
        // Revogar todas as licenças anteriores deste tenant
        await pool.query(
            `UPDATE client_licenses 
             SET status = 'revoked', updated_at = NOW() 
             WHERE tenant_id = ? AND status IN ('active', 'trial')`,
            [tenantId]
        );

        const licenseKey = this.generateLicenseKey();
        
        const defaultModules: ModulesConfig = {
            static_site: { enabled: true, ai_generated: false, theme: 'dark' },
            bot_widget: { enabled: false, type: 'faq', features: ['info'], exportable: false, position: 'floating' },
            bot_whatsapp: { enabled: false, features: ['info'] },
            ai_calls: { enabled: false },
            email: { enabled: false, provider: 'smtp' },
            sms: { enabled: false, provider: 'twilio' },
            shop: { enabled: false, platform: 'prestashop' },
        };

        // Merge custom modules
        const finalModules = { ...defaultModules, ...modules };

        // Set trial period (30 days)
        const trialEndsAt = new Date();
        trialEndsAt.setDate(trialEndsAt.getDate() + 30);

        const billingStart = new Date();
        const billingEnd = new Date();
        billingEnd.setMonth(billingEnd.getMonth() + 1);

        const [result] = await pool.query<ResultSetHeader>(
            `INSERT INTO client_licenses 
                (tenant_id, license_key, client_name, client_email, modules, 
                 ai_messages_limit, email_limit, sms_limit, whatsapp_limit, ai_calls_limit,
                 billing_cycle_start, billing_cycle_end, trial_ends_at, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'trial')`,
            [
                tenantId,
                licenseKey,
                clientName,
                clientEmail || null,
                JSON.stringify(finalModules),
                limits?.ai_messages_limit || 100,
                limits?.email_limit || 500,
                limits?.sms_limit || 100,
                limits?.whatsapp_limit || 500,
                limits?.ai_calls_limit || 50,
                billingStart,
                billingEnd,
                trialEndsAt,
            ]
        );

        return this.getLicenseById(result.insertId);
    }

    /**
     * Obtém licença por ID
     */
    static async getLicenseById(id: number): Promise<License> {
        const [rows] = await pool.query<LicenseRow[]>(
            'SELECT * FROM client_licenses WHERE id = ?',
            [id]
        );
        
        if (rows.length === 0) {
            throw new Error('License not found');
        }
        
        return parseLicenseRow(rows[0]);
    }

    /**
     * Obtém licença por chave
     */
    static async getLicenseByKey(licenseKey: string): Promise<License | null> {
        const [rows] = await pool.query<LicenseRow[]>(
            'SELECT * FROM client_licenses WHERE license_key = ?',
            [licenseKey]
        );
        
        if (rows.length === 0) {
            return null;
        }
        
        return parseLicenseRow(rows[0]);
    }

    /**
     * Valida licença e módulo específico
     */
    static async validateLicense(
        licenseKey: string,
        moduleName?: keyof ModulesConfig
    ): Promise<{ valid: boolean; error?: string; license?: License }> {
        const license = await this.getLicenseByKey(licenseKey);
        
        if (!license) {
            return { valid: false, error: 'Invalid license key' };
        }

        // Check status
        if (license.status === 'suspended') {
            return { valid: false, error: 'License suspended' };
        }
        
        if (license.status === 'expired') {
            return { valid: false, error: 'License expired' };
        }

        if (license.status === 'revoked') {
            return { valid: false, error: 'License revoked - a new license was issued' };
        }

        // Check trial
        if (license.status === 'trial' && license.trial_ends_at) {
            if (new Date() > new Date(license.trial_ends_at)) {
                return { valid: false, error: 'Trial period ended' };
            }
        }

        // Check specific module
        if (moduleName) {
            const moduleConfig = license.modules[moduleName];
            if (!moduleConfig || !moduleConfig.enabled) {
                return { valid: false, error: `Module ${moduleName} not enabled` };
            }
        }

        return { valid: true, license };
    }

    /**
     * Verifica créditos disponíveis
     */
    static async checkCredits(
        licenseKey: string,
        usageType: string
    ): Promise<{ hasCredits: boolean; used: number; limit: number; remaining: number }> {
        const license = await this.getLicenseByKey(licenseKey);
        
        if (!license) {
            return { hasCredits: false, used: 0, limit: 0, remaining: 0 };
        }

        const usage = USAGE_TYPES[usageType];
        if (!usage) {
            return { hasCredits: false, used: 0, limit: 0, remaining: 0 };
        }

        const used = (license as any)[usage.usedField] || 0;
        const limit = (license as any)[usage.limitField] || 0;
        const remaining = Math.max(0, limit - used);

        return {
            hasCredits: remaining > 0,
            used,
            limit,
            remaining,
        };
    }

    /**
     * Consome créditos
     */
    static async consumeCredits(
        licenseKey: string,
        usageType: string,
        amount: number = 1,
        metadata?: Record<string, any>
    ): Promise<boolean> {
        const license = await this.getLicenseByKey(licenseKey);
        
        if (!license) {
            return false;
        }

        const usage = USAGE_TYPES[usageType];
        if (!usage) {
            return false;
        }

        // Update usage counter
        await pool.query(
            `UPDATE client_licenses SET ${usage.usedField} = ${usage.usedField} + ? WHERE id = ?`,
            [amount, license.id]
        );

        // Log usage
        await pool.query<ResultSetHeader>(
            `INSERT INTO usage_logs (license_id, usage_type, tokens_used, metadata) VALUES (?, ?, ?, ?)`,
            [license.id, usage.type, metadata?.tokens || 0, metadata ? JSON.stringify(metadata) : null]
        );

        return true;
    }

    /**
     * Obtém estatísticas de uso
     */
    static async getUsageStats(licenseKey: string, period?: { start: Date; end: Date }): Promise<{
        total: Record<string, number>;
        byDay: Array<{ date: string; type: string; count: number }>;
    }> {
        const license = await this.getLicenseByKey(licenseKey);
        
        if (!license) {
            return { total: {}, byDay: [] };
        }

        let query = `
            SELECT usage_type, COUNT(*) as count, DATE(created_at) as date
            FROM usage_logs 
            WHERE license_id = ?
        `;
        const params: any[] = [license.id];

        if (period) {
            query += ' AND created_at BETWEEN ? AND ?';
            params.push(period.start, period.end);
        }

        query += ' GROUP BY usage_type, DATE(created_at) ORDER BY date DESC';

        const [rows] = await pool.query<RowDataPacket[]>(query, params);

        const total: Record<string, number> = {};
        const byDay: Array<{ date: string; type: string; count: number }> = [];

        for (const row of rows) {
            const type = row.usage_type;
            total[type] = (total[type] || 0) + Number(row.count);
            byDay.push({
                date: new Date(row.date).toISOString().split('T')[0],
                type,
                count: Number(row.count),
            });
        }

        return { total, byDay };
    }

    /**
     * Atualiza módulos
     */
    static async updateModules(licenseKey: string, modules: Partial<ModulesConfig>): Promise<License> {
        const license = await this.getLicenseByKey(licenseKey);
        
        if (!license) {
            throw new Error('Invalid license key');
        }

        const updatedModules = { ...license.modules, ...modules };

        await pool.query(
            'UPDATE client_licenses SET modules = ? WHERE id = ?',
            [JSON.stringify(updatedModules), license.id]
        );

        return this.getLicenseById(license.id);
    }

    /**
     * Reset usage counters (para novo ciclo de faturação)
     */
    static async resetUsageCounters(licenseId: number): Promise<void> {
        const newBillingStart = new Date();
        const newBillingEnd = new Date();
        newBillingEnd.setMonth(newBillingEnd.getMonth() + 1);

        await pool.query(
            `UPDATE client_licenses SET 
                ai_messages_used = 0,
                emails_sent = 0,
                sms_sent = 0,
                whatsapp_sent = 0,
                ai_calls_used = 0,
                billing_cycle_start = ?,
                billing_cycle_end = ?
             WHERE id = ?`,
            [newBillingStart, newBillingEnd, licenseId]
        );
    }

    /**
     * Lista todas as licenças
     */
    static async listLicenses(status?: string): Promise<License[]> {
        let query = 'SELECT * FROM client_licenses';
        const params: any[] = [];

        if (status) {
            query += ' WHERE status = ?';
            params.push(status);
        }

        query += ' ORDER BY created_at DESC';

        const [rows] = await pool.query<LicenseRow[]>(query, params);
        
        return rows.map(parseLicenseRow);
    }

    /**
     * Ativa licença
     */
    static async activateLicense(licenseKey: string): Promise<License> {
        const license = await this.getLicenseByKey(licenseKey);
        if (!license) throw new Error('Invalid license key');

        await pool.query(
            'UPDATE client_licenses SET status = ? WHERE id = ?',
            ['active', license.id]
        );

        return this.getLicenseById(license.id);
    }

    /**
     * Suspende licença
     */
    static async suspendLicense(licenseKey: string): Promise<License> {
        const license = await this.getLicenseByKey(licenseKey);
        if (!license) throw new Error('Invalid license key');

        await pool.query(
            'UPDATE client_licenses SET status = ? WHERE id = ?',
            ['suspended', license.id]
        );

        return this.getLicenseById(license.id);
    }

    /**
     * Obtém licenças por tenant
     */
    static async getLicensesByTenant(tenantId: number): Promise<License[]> {
        const [rows] = await pool.query<LicenseRow[]>(
            'SELECT * FROM client_licenses WHERE tenant_id = ? ORDER BY created_at DESC',
            [tenantId]
        );

        return rows.map(parseLicenseRow);
    }
}
