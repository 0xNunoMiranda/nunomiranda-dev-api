import pool from '../db';
import logger from '../logger';
import { createBadRequest } from '../errors';
import { LicenseService } from './licenseService';
import { AIService } from './aiService';

type WhatsAppConnectionState = 'disconnected' | 'connecting' | 'qr' | 'connected' | 'error';

type SessionConfig = {
  siteUrl: string | null;
  messagesPerMinute: number; // 1..5
  promptTemplate: string;
  tags: { bookings: boolean; faqs: boolean; shop: boolean };
};

type SiteForgeContext = {
  tags: { bookings: string; faqs: string; shop: string };
  whatsappSettings: Record<string, unknown> | null;
};

type ConnectResult = {
  state: WhatsAppConnectionState;
  qr?: string | null;
  qrDataUrl?: string | null;
  lastError?: string | null;
};

type StatusResult = {
  state: WhatsAppConnectionState;
  phoneNumber?: string | null;
  deviceJid?: string | null;
  lastError?: string | null;
  lastQr?: string | null;
  siteUrl?: string | null;
};

type ActiveSession = {
  licenseKey: string;
  licenseId: number;
  tenantId: number;
  config: SessionConfig;
  state: WhatsAppConnectionState;
  qr: string | null;
  lastError: string | null;
  sock: any | null;
  reconnectAttempts: number;
  reconnectTimer: NodeJS.Timeout | null;
  sendTimestamps: number[];
  persistTimer: NodeJS.Timeout | null;
  authState: any;
};

const clampMessagesPerMinute = (value: unknown) => {
  const parsed = Number(value);
  if (!Number.isFinite(parsed)) return 5;
  return Math.max(1, Math.min(5, Math.floor(parsed)));
};

const requireBaileys = () => {
  try {
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    return require('@whiskeysockets/baileys');
  } catch (error) {
    return null;
  }
};

const requireQrcode = () => {
  try {
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    return require('qrcode');
  } catch (error) {
    return null;
  }
};

const bearerTokenToLicenseKey = (bearerToken: string) => bearerToken.trim();

const defaultConfig = (): SessionConfig => ({
  siteUrl: null,
  messagesPerMinute: 5,
  promptTemplate: '',
  tags: { bookings: true, faqs: true, shop: true },
});

const normalizeSiteUrl = (value: unknown) => {
  if (typeof value !== 'string') return null;
  const trimmed = value.trim().replace(/\/+$/, '');
  return trimmed ? trimmed : null;
};

const safeJsonParse = <T>(value: string | null): T | null => {
  if (!value) return null;
  try {
    return JSON.parse(value) as T;
  } catch (error) {
    return null;
  }
};

const fetchJson = async (url: string, options: RequestInit) => {
  // Node 18+ has global fetch.
  if (typeof fetch !== 'function') {
    throw new Error('fetch is not available in this Node runtime');
  }
  const res = await fetch(url, options);
  const text = await res.text();
  let data: any = null;
  try {
    data = JSON.parse(text);
  } catch {
    // ignore
  }
  return { ok: res.ok, status: res.status, data, text };
};

class WhatsAppWebServiceImpl {
  private sessions = new Map<number, ActiveSession>();

  private async upsertSessionRow(params: {
    tenantId: number;
    licenseId: number;
    siteUrl: string | null;
  }) {
    await pool.query(
      `INSERT INTO whatsapp_web_sessions (tenant_id, license_id, site_url)
       VALUES (?, ?, ?)
       ON DUPLICATE KEY UPDATE tenant_id = VALUES(tenant_id), site_url = VALUES(site_url)`,
      [params.tenantId, params.licenseId, params.siteUrl],
    );
  }

  private async updateSessionRow(
    licenseId: number,
    updates: Partial<{
      connection_state: WhatsAppConnectionState;
      phone_number: string | null;
      device_jid: string | null;
      auth_state_json: string | null;
      last_qr: string | null;
      last_error: string | null;
      connected_at: Date | null;
      disconnected_at: Date | null;
    }>,
  ) {
    const fields = Object.keys(updates);
    if (fields.length === 0) return;
    const setSql = fields.map((field) => `${field} = ?`).join(', ');
    const values = fields.map((field) => (updates as any)[field]);
    await pool.query(`UPDATE whatsapp_web_sessions SET ${setSql} WHERE license_id = ?`, [...values, licenseId]);
  }

  private async loadSessionRow(licenseId: number) {
    const [rows] = await pool.query<any[]>(
      'SELECT * FROM whatsapp_web_sessions WHERE license_id = ? LIMIT 1',
      [licenseId],
    );
    return rows[0] || null;
  }

  private schedulePersist(session: ActiveSession) {
    if (session.persistTimer) return;
    session.persistTimer = setTimeout(async () => {
      session.persistTimer = null;
      try {
        const baileys = requireBaileys();
        if (!baileys) return;
        const { BufferJSON } = baileys;
        const serialized = JSON.stringify(session.authState, BufferJSON.replacer);
        await this.updateSessionRow(session.licenseId, { auth_state_json: serialized });
      } catch (error) {
        logger.warn({ error, licenseId: session.licenseId }, 'Failed to persist WhatsApp auth state');
      }
    }, 750);
  }

  private canSendNow(session: ActiveSession) {
    const limit = clampMessagesPerMinute(session.config.messagesPerMinute);
    const now = Date.now();
    session.sendTimestamps = session.sendTimestamps.filter((t) => now - t < 60_000);
    return session.sendTimestamps.length < limit;
  }

  private recordSend(session: ActiveSession) {
    session.sendTimestamps.push(Date.now());
  }

  private async getSiteForgeContext(session: ActiveSession): Promise<SiteForgeContext> {
    const enabled = session.config.tags ?? { bookings: true, faqs: true, shop: true };
    if (!session.config.siteUrl) return { tags: { bookings: '', faqs: '', shop: '' }, whatsappSettings: null };
    const url = `${session.config.siteUrl}/api/whatsapp-context.php`;
    const result = await fetchJson(url, {
      method: 'GET',
      headers: {
        Accept: 'application/json',
        'X-SiteForge-License': session.licenseKey,
      },
    });
    if (!result.ok) return { tags: { bookings: '', faqs: '', shop: '' }, whatsappSettings: null };
    const tags = result.data?.data?.tags;
    const whatsappSettingsRaw = result.data?.data?.settings?.whatsapp;
    return {
      tags: {
        bookings: enabled.bookings && typeof tags?.bookings === 'string' ? tags.bookings : '',
        faqs: enabled.faqs && typeof tags?.faqs === 'string' ? tags.faqs : '',
        shop: enabled.shop && typeof tags?.shop === 'string' ? tags.shop : '',
      },
      whatsappSettings:
        whatsappSettingsRaw && typeof whatsappSettingsRaw === 'object' ? (whatsappSettingsRaw as any) : null,
    };
  }

  private async getTagContext(session: ActiveSession) {
    const enabled = session.config.tags ?? { bookings: true, faqs: true, shop: true };
    if (!enabled.bookings && !enabled.faqs && !enabled.shop) return { bookings: '', faqs: '', shop: '' };
    const ctx = await this.getSiteForgeContext(session);
    return ctx.tags;
  }

  private applyPromptTemplate(template: string, context: { bookings: string; faqs: string; shop: string }) {
    return template
      .replace(/{{\s*bookings\s*}}/g, context.bookings || '')
      .replace(/{{\s*faqs\s*}}/g, context.faqs || '')
      .replace(/{{\s*shop\s*}}/g, context.shop || '');
  }

  private async logToSiteForge(
    session: ActiveSession,
    payload: Record<string, unknown>,
  ) {
    if (!session.config.siteUrl) return;
    const url = `${session.config.siteUrl}/api/whatsapp-webhook.php`;
    try {
      await fetchJson(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-SiteForge-License': session.licenseKey,
        },
        body: JSON.stringify(payload),
      });
    } catch (error) {
      logger.warn({ error, licenseId: session.licenseId }, 'Failed to log WhatsApp message to SiteForge');
    }
  }

  async connect(bearerToken: string, tenantId: number, input?: Partial<SessionConfig>): Promise<ConnectResult> {
    const baileys = requireBaileys();
    if (!baileys) {
      throw createBadRequest(
        'WhatsApp Web indisponível: instala @whiskeysockets/baileys (e opcionalmente qrcode) e reinicia o Node.',
      );
    }

    const licenseKey = bearerTokenToLicenseKey(bearerToken);
    const validation = await LicenseService.validateLicense(licenseKey);
    if (!validation.valid || !validation.license) {
      throw createBadRequest(validation.error || 'Invalid license');
    }

    // Convenience: connecting WhatsApp Web implies the WhatsApp bot module should be enabled for this license.
    const license = validation.license;
    if (!license.modules?.bot_whatsapp?.enabled) {
      await LicenseService.updateModules(licenseKey, {
        bot_whatsapp: {
          enabled: true,
          features: license.modules?.bot_whatsapp?.features || ['info'],
          phone_number: license.modules?.bot_whatsapp?.phone_number,
        },
      });
    }

    const existing = this.sessions.get(license.id);
    if (existing && (existing.state === 'connecting' || existing.state === 'qr' || existing.state === 'connected')) {
      const qrDataUrl = await this.qrToDataUrl(existing.qr);
      return { state: existing.state, qr: existing.qr, qrDataUrl };
    }

    const cfg = defaultConfig();
    cfg.siteUrl = normalizeSiteUrl(input?.siteUrl ?? cfg.siteUrl);
    cfg.messagesPerMinute = clampMessagesPerMinute(input?.messagesPerMinute ?? cfg.messagesPerMinute);
    cfg.promptTemplate = typeof input?.promptTemplate === 'string' ? input.promptTemplate : cfg.promptTemplate;
    cfg.tags = input?.tags ? { ...cfg.tags, ...input.tags } : cfg.tags;

    await this.upsertSessionRow({ tenantId, licenseId: license.id, siteUrl: cfg.siteUrl });

    const row = await this.loadSessionRow(license.id);
    const authStateJson = row?.auth_state_json as string | null;

    const { BufferJSON, initAuthCreds, makeWASocket, DisconnectReason, fetchLatestBaileysVersion } = baileys;

    const loaded = authStateJson ? JSON.parse(authStateJson, BufferJSON.reviver) : null;
    const authState = loaded || { creds: initAuthCreds(), keys: {} };

    const keysStore = {
      get: async (type: string, ids: string[]) => {
        const bucket = authState.keys?.[type] || {};
        const out: Record<string, any> = {};
        for (const id of ids) {
          const value = bucket[id];
          if (value) {
            out[id] = value;
          }
        }
        return out;
      },
      set: async (data: Record<string, Record<string, any>>) => {
        authState.keys = authState.keys || {};
        for (const type of Object.keys(data)) {
          authState.keys[type] = authState.keys[type] || {};
          for (const id of Object.keys(data[type] || {})) {
            const value = data[type][id];
            if (value) {
              authState.keys[type][id] = value;
            } else {
              delete authState.keys[type][id];
            }
          }
        }
      },
    };

    const resolveVersion = async (): Promise<any | undefined> => {
      try {
        if (typeof fetchLatestBaileysVersion !== 'function') return undefined;
        const result = await fetchLatestBaileysVersion();
        return result?.version;
      } catch {
        return undefined;
      }
    };

    const socketVersion = await resolveVersion();
    const socketBrowser =
      baileys?.Browsers?.ubuntu?.('Chrome') ||
      baileys?.Browsers?.windows?.('Chrome') ||
      ['Ubuntu', 'Chrome', '22.04.4'];

    const startSocket = async () => {
      const sock = makeWASocket({
        auth: {
          creds: authState.creds,
          keys: keysStore,
        },
        printQRInTerminal: false,
        shouldIgnoreJid: () => false,
        browser: socketBrowser,
        version: socketVersion,
        connectTimeoutMs: 45_000,
        keepAliveIntervalMs: 30_000,
        markOnlineOnConnect: false,
        syncFullHistory: false,
      });
      session.sock = sock;
      return sock;
    };

    const session: ActiveSession = {
      licenseKey,
      licenseId: license.id,
      tenantId,
      config: cfg,
      state: 'connecting',
      qr: null,
      lastError: null,
      sock: null,
      reconnectAttempts: 0,
      reconnectTimer: null,
      sendTimestamps: [],
      persistTimer: null,
      authState,
    };

    this.sessions.set(license.id, session);
    await this.updateSessionRow(license.id, {
      connection_state: 'connecting',
      last_error: null,
      disconnected_at: null,
    });

    const attachSocketHandlers = (sockInstance: any) => {
      const extractText = (message: any): string | null => {
        if (!message) return null;
        if (message.ephemeralMessage?.message) return extractText(message.ephemeralMessage.message);
        if (message.viewOnceMessage?.message) return extractText(message.viewOnceMessage.message);
        if (message.conversation) return message.conversation;
        if (message.extendedTextMessage?.text) return message.extendedTextMessage.text;
        if (message.imageMessage?.caption) return message.imageMessage.caption;
        if (message.videoMessage?.caption) return message.videoMessage.caption;
        if (message.documentMessage?.caption) return message.documentMessage.caption;
        return null;
      };

      sockInstance.ev.on('creds.update', () => {
        this.schedulePersist(session);
      });

      sockInstance.ev.on('keys.set', () => {
        this.schedulePersist(session);
      });

      sockInstance.ev.on('connection.update', async (update: any) => {
        const qr = update.qr || null;
        if (qr) {
          session.state = 'qr';
          session.qr = qr;
          session.lastError = null;
          session.reconnectAttempts = 0;
          await this.updateSessionRow(license.id, { connection_state: 'qr', last_qr: qr });
        }

        if (update.connection === 'open') {
          session.state = 'connected';
          const jid = sockInstance.user?.id || null;
          session.qr = null;
          session.lastError = null;
          session.reconnectAttempts = 0;
          await this.updateSessionRow(license.id, {
            connection_state: 'connected',
            device_jid: jid,
            last_qr: null,
            connected_at: new Date(),
            last_error: null,
          });
        }

        if (update.connection === 'close') {
          const err = update?.lastDisconnect?.error;
          const message =
            err?.message ||
            err?.output?.payload?.message ||
            err?.data?.message ||
            (err ? String(err) : null);
          const payload = err?.output?.payload;
          const payloadText =
            payload && typeof payload === 'object'
              ? (() => {
                  try {
                    const s = JSON.stringify(payload);
                    return s.length > 600 ? s.slice(0, 600) + '…' : s;
                  } catch {
                    return null;
                  }
                })()
              : null;
          const statusCode = update?.lastDisconnect?.error?.output?.statusCode;
          const reason = statusCode ?? null;
          const isLoggedOut = reason === DisconnectReason.loggedOut;
          const hint =
            statusCode === 405
              ? 'Dica: isto costuma acontecer quando a versão do WhatsApp Web mudou ou o WhatsApp bloqueou tentativas novas. Tenta “Desligar”, esperar 2-5 min, e voltar a ligar (ou trocar de rede/IP).'
              : null;
          const details = [message, statusCode ? `statusCode=${statusCode}` : null, payloadText, hint]
            .filter(Boolean)
            .join(' | ');
          session.lastError = details || null;
          session.state = isLoggedOut ? 'disconnected' : 'error';
          await this.updateSessionRow(license.id, {
            connection_state: session.state,
            disconnected_at: new Date(),
            last_error: session.lastError,
          });
          if (isLoggedOut) {
            // wipe auth state to force new QR
            session.authState = { creds: initAuthCreds(), keys: {} };
            await this.updateSessionRow(license.id, { auth_state_json: null });
            return;
          }

          // Auto-retry transient failures (network flakiness, WA hiccups) by reconnecting with backoff.
          const maxAttempts = 6;
          if (session.reconnectAttempts >= maxAttempts) return;
          session.reconnectAttempts++;
          const delayMs = Math.min(30_000, 1_000 * Math.pow(2, session.reconnectAttempts)); // 2s..30s
          if (session.reconnectTimer) clearTimeout(session.reconnectTimer);
          session.reconnectTimer = setTimeout(async () => {
            try {
              try {
                session.sock?.end?.();
              } catch {
                // ignore
              }
              session.state = 'connecting';
              await this.updateSessionRow(license.id, { connection_state: 'connecting' });
              const newSock = await startSocket();
              attachSocketHandlers(newSock);
            } catch (error) {
              logger.warn({ error, licenseId: session.licenseId }, 'WhatsApp reconnect attempt failed');
            }
          }, delayMs);
        }
      });

      sockInstance.ev.on('messages.upsert', async (evt: any) => {
        try {
          const msg = evt?.messages?.[0];
          if (!msg) return;
          if (msg.key?.fromMe) return;

          const remoteJid = msg.key?.remoteJid as string | undefined;
          if (!remoteJid) return;
          if (remoteJid === 'status@broadcast') return;
          if (remoteJid.endsWith('@g.us')) return; // groups

          const textRaw = extractText(msg.message);
          const text = typeof textRaw === 'string' ? textRaw.trim() : '';
          if (!text) return;

          const sessionId = remoteJid;
          const phone = remoteJid.split('@')[0];

          await this.logToSiteForge(session, {
            direction: 'inbound',
            sessionId,
            phone,
            content: text,
          });

          // Avoid spending AI credits if we are rate-limited.
          if (!this.canSendNow(session)) {
            return;
          }

          const siteforge = await this.getSiteForgeContext(session);
          const context = siteforge.tags;
          const greeting =
            typeof siteforge.whatsappSettings?.greeting === 'string'
              ? String(siteforge.whatsappSettings.greeting).trim()
              : '';
          const systemPrompt =
            session.config.promptTemplate && session.config.promptTemplate.trim()
              ? this.applyPromptTemplate(session.config.promptTemplate, context)
              : '';

          const ai = await AIService.chat(
            licenseKey,
            [
              {
                role: 'system',
                content:
                  systemPrompt ||
                  'És um assistente no WhatsApp. Responde em português de Portugal e sê conciso.',
              },
              { role: 'user', content: text },
            ],
            { maxTokens: 250, temperature: 0.7, module: 'bot_whatsapp' },
          );

          const reply = ai.success && ai.content ? String(ai.content).trim() : greeting || 'Obrigado pela mensagem!';
          if (!reply) return;
          await sockInstance.sendMessage(remoteJid, { text: reply });
          this.recordSend(session);

          await LicenseService.consumeCredits(licenseKey, 'whatsapp', 1, { channel: 'whatsapp' });

          await this.logToSiteForge(session, {
            direction: 'outbound',
            sessionId,
            phone,
            content: reply,
          });
        } catch (error) {
          logger.warn({ error, licenseId: session.licenseId }, 'WhatsApp message handler failed');
        }
      });
    };

    const sock = await startSocket();
    attachSocketHandlers(sock);

    // Wait a bit so the API caller can immediately render the QR (or see an error/connected state).
    const waitStart = Date.now();
    while (Date.now() - waitStart < 8_000) {
      if (session.state === 'qr' || session.state === 'connected' || session.state === 'error') break;
      await new Promise((r) => setTimeout(r, 250));
    }

    const qrDataUrl = await this.qrToDataUrl(session.qr);
    return { state: session.state, qr: session.qr, qrDataUrl, lastError: session.lastError };
  }

  async disconnect(bearerToken: string) {
    const licenseKey = bearerTokenToLicenseKey(bearerToken);
    const license = await LicenseService.getLicenseByKey(licenseKey);
    if (!license) {
      throw new Error('License not found');
    }
    const session = this.sessions.get(license.id);
    if (session?.sock) {
      try {
        await session.sock.logout();
      } catch {
        // ignore
      }
      try {
        session.sock.end?.();
      } catch {
        // ignore
      }
    }
    this.sessions.delete(license.id);
    await this.updateSessionRow(license.id, {
      connection_state: 'disconnected',
      disconnected_at: new Date(),
    });
    return { state: 'disconnected' as const };
  }

  async status(bearerToken: string): Promise<StatusResult> {
    const licenseKey = bearerTokenToLicenseKey(bearerToken);
    const license = await LicenseService.getLicenseByKey(licenseKey);
    if (!license) {
      throw new Error('License not found');
    }

    const active = this.sessions.get(license.id);
    if (active) {
      return {
        state: active.state,
        deviceJid: active.sock?.user?.id ?? null,
        lastQr: active.qr,
        lastError: active.lastError,
        siteUrl: active.config.siteUrl,
      };
    }

    const row = await this.loadSessionRow(license.id);
    if (!row) {
      return { state: 'disconnected' };
    }
    return {
      state: (row.connection_state as WhatsAppConnectionState) || 'disconnected',
      phoneNumber: row.phone_number ?? null,
      deviceJid: row.device_jid ?? null,
      lastError: row.last_error ?? null,
      lastQr: row.last_qr ?? null,
      siteUrl: row.site_url ?? null,
    };
  }

  private async qrToDataUrl(qr: string | null) {
    if (!qr) return null;
    const qrcode = requireQrcode();
    if (!qrcode) return null;
    try {
      return await qrcode.toDataURL(qr, { margin: 1, width: 280 });
    } catch {
      return null;
    }
  }
}

export const whatsappWebService = new WhatsAppWebServiceImpl();
export default whatsappWebService;
