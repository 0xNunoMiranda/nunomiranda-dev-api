import { Router, Request, Response } from 'express';
import { LicenseService, ModulesConfig } from '../services/licenseService';
import { AIService } from '../services/aiService';
import { asyncHandler } from '../middleware/errorHandler';
import { z } from 'zod';
import { validateRequest } from '../middleware/errorHandler';

const router = Router();

// ============================================
// LICENÇAS
// ============================================

/**
 * POST /api/licenses - Criar nova licença
 */
const createLicenseSchema = z.object({
    body: z.object({
        tenantId: z.number(),
        clientName: z.string().min(1),
        clientEmail: z.string().email().optional(),
        modules: z.record(z.any()).optional(),
        limits: z.object({
            ai_messages_limit: z.number().optional(),
            email_limit: z.number().optional(),
            sms_limit: z.number().optional(),
            whatsapp_limit: z.number().optional(),
            ai_calls_limit: z.number().optional(),
        }).optional(),
    }),
});

router.post('/licenses', validateRequest(createLicenseSchema), asyncHandler(async (req: Request, res: Response) => {
    const { tenantId, clientName, clientEmail, modules, limits } = req.body;
    
    const license = await LicenseService.createLicense(
        tenantId,
        clientName,
        clientEmail,
        modules,
        limits
    );

    res.status(201).json({
        success: true,
        license: {
            id: license.id,
            licenseKey: license.license_key,
            clientName: license.client_name,
            status: license.status,
            trialEndsAt: license.trial_ends_at,
            modules: license.modules,
        },
    });
}));

/**
 * GET /api/licenses/:key/validate - Validar licença
 */
router.get('/licenses/:key/validate', asyncHandler(async (req: Request, res: Response) => {
    const { key } = req.params;
    const { module } = req.query;

    const validation = await LicenseService.validateLicense(
        key,
        module as keyof ModulesConfig | undefined
    );

    if (!validation.valid) {
        return res.status(403).json({
            valid: false,
            error: validation.error,
        });
    }

    res.json({
        valid: true,
        license: {
            clientName: validation.license!.client_name,
            status: validation.license!.status,
            modules: validation.license!.modules,
            credits: {
                ai: {
                    used: validation.license!.ai_messages_used,
                    limit: validation.license!.ai_messages_limit,
                    remaining: validation.license!.ai_messages_limit - validation.license!.ai_messages_used,
                },
                email: {
                    used: validation.license!.emails_sent,
                    limit: validation.license!.email_limit,
                    remaining: validation.license!.email_limit - validation.license!.emails_sent,
                },
                sms: {
                    used: validation.license!.sms_sent,
                    limit: validation.license!.sms_limit,
                    remaining: validation.license!.sms_limit - validation.license!.sms_sent,
                },
                whatsapp: {
                    used: validation.license!.whatsapp_sent,
                    limit: validation.license!.whatsapp_limit,
                    remaining: validation.license!.whatsapp_limit - validation.license!.whatsapp_sent,
                },
            },
        },
    });
}));

/**
 * GET /api/licenses/:key/credits - Ver créditos disponíveis
 */
router.get('/licenses/:key/credits', asyncHandler(async (req: Request, res: Response) => {
    const { key } = req.params;
    const { type } = req.query;

    if (type) {
        const credits = await LicenseService.checkCredits(key, type as any);
        return res.json({ success: true, credits });
    }

    // Retornar todos os tipos de créditos
    const [ai, email, sms, whatsapp, calls] = await Promise.all([
        LicenseService.checkCredits(key, 'ai_message'),
        LicenseService.checkCredits(key, 'email'),
        LicenseService.checkCredits(key, 'sms'),
        LicenseService.checkCredits(key, 'whatsapp'),
        LicenseService.checkCredits(key, 'ai_call'),
    ]);

    res.json({
        success: true,
        credits: {
            ai_messages: ai,
            email: email,
            sms: sms,
            whatsapp: whatsapp,
            ai_calls: calls,
        },
    });
}));

/**
 * PUT /api/licenses/:key/modules - Atualizar módulos
 */
router.put('/licenses/:key/modules', asyncHandler(async (req: Request, res: Response) => {
    const { key } = req.params;
    const { modules } = req.body;

    const license = await LicenseService.updateModules(key, modules);

    res.json({
        success: true,
        modules: license.modules,
    });
}));

/**
 * GET /api/licenses/:key/usage - Estatísticas de uso
 */
router.get('/licenses/:key/usage', asyncHandler(async (req: Request, res: Response) => {
    const { key } = req.params;
    const days = parseInt(req.query.days as string) || 30;

    // Calculate period
    const end = new Date();
    const start = new Date();
    start.setDate(start.getDate() - days);

    const stats = await LicenseService.getUsageStats(key, { start, end });

    res.json({
        success: true,
        period: `${days} days`,
        usage: stats,
    });
}));

/**
 * POST /api/licenses/:key/activate - Ativar licença
 */
router.post('/licenses/:key/activate', asyncHandler(async (req: Request, res: Response) => {
    const { key } = req.params;
    
    const license = await LicenseService.activateLicense(key);

    res.json({
        success: true,
        status: license.status,
    });
}));

/**
 * POST /api/licenses/:key/suspend - Suspender licença
 */
router.post('/licenses/:key/suspend', asyncHandler(async (req: Request, res: Response) => {
    const { key } = req.params;
    
    const license = await LicenseService.suspendLicense(key);

    res.json({
        success: true,
        status: license.status,
    });
}));

/**
 * GET /api/licenses - Listar todas as licenças (admin)
 */
router.get('/licenses', asyncHandler(async (req: Request, res: Response) => {
    const { status } = req.query;
    
    const licenses = await LicenseService.listLicenses(status as string | undefined);

    res.json({
        success: true,
        count: licenses.length,
        licenses: licenses.map((l: any) => ({
            id: l.id,
            licenseKey: l.license_key,
            clientName: l.client_name,
            clientEmail: l.client_email,
            status: l.status,
            modules: l.modules,
            credits: {
                ai: `${l.ai_messages_used}/${l.ai_messages_limit}`,
                email: `${l.emails_sent}/${l.email_limit}`,
                sms: `${l.sms_sent}/${l.sms_limit}`,
            },
        })),
    });
}));

// ============================================
// BOT / AI
// ============================================

/**
 * POST /api/bot/message - Enviar mensagem para o bot
 */
const botMessageSchema = z.object({
    body: z.object({
        licenseKey: z.string(),
        sessionId: z.string(),
        message: z.string().min(1),
        channel: z.enum(['widget', 'whatsapp', 'api']).optional(),
    }),
});

router.post('/bot/message', validateRequest(botMessageSchema), asyncHandler(async (req: Request, res: Response) => {
    const { licenseKey, sessionId, message, channel } = req.body;

    const result = await AIService.handleBotMessage(
        licenseKey,
        sessionId,
        message,
        channel || 'widget'
    );

    if (!result.success) {
        return res.status(400).json({
            success: false,
            error: result.error,
        });
    }

    res.json({
        success: true,
        reply: result.content,
        tokensUsed: result.tokensUsed,
    });
}));

/**
 * POST /api/ai/generate-site - Gerar conteúdo do site com AI
 */
const generateSiteSchema = z.object({
    body: z.object({
        licenseKey: z.string(),
        businessInfo: z.object({
            name: z.string(),
            description: z.string(),
            services: z.array(z.string()).optional(),
            phone: z.string().optional(),
            email: z.string().optional(),
            address: z.string().optional(),
            style: z.enum(['modern', 'classic', 'minimal']).optional(),
        }),
    }),
});

router.post('/ai/generate-site', validateRequest(generateSiteSchema), asyncHandler(async (req: Request, res: Response) => {
    const { licenseKey, businessInfo } = req.body;

    const result = await AIService.generateSite(licenseKey, businessInfo);

    if (!result.success) {
        return res.status(400).json({
            success: false,
            error: result.error,
        });
    }

    // Tentar parse do JSON
    let siteContent;
    try {
        siteContent = JSON.parse(result.content!);
    } catch {
        siteContent = result.content;
    }

    res.json({
        success: true,
        content: siteContent,
        tokensUsed: result.tokensUsed,
    });
}));

/**
 * POST /api/ai/generate-faqs - Gerar FAQs com AI
 */
const generateFaqsSchema = z.object({
    body: z.object({
        licenseKey: z.string(),
        businessInfo: z.object({
            name: z.string(),
            description: z.string(),
            services: z.array(z.string()).optional(),
        }),
        count: z.number().min(1).max(20).optional(),
    }),
});

router.post('/ai/generate-faqs', validateRequest(generateFaqsSchema), asyncHandler(async (req: Request, res: Response) => {
    const { licenseKey, businessInfo, count } = req.body;

    const result = await AIService.generateFaqs(licenseKey, businessInfo, count || 10);

    if (!result.success) {
        return res.status(400).json({
            success: false,
            error: result.error,
        });
    }

    let faqs;
    try {
        faqs = JSON.parse(result.content!);
    } catch {
        faqs = result.content;
    }

    res.json({
        success: true,
        faqs,
        tokensUsed: result.tokensUsed,
    });
}));

/**
 * POST /api/ai/chat - Chat direto com AI
 */
const chatSchema = z.object({
    body: z.object({
        licenseKey: z.string(),
        messages: z.array(z.object({
            role: z.enum(['system', 'user', 'assistant']),
            content: z.string(),
        })),
        options: z.object({
            maxTokens: z.number().optional(),
            temperature: z.number().optional(),
        }).optional(),
    }),
});

router.post('/ai/chat', validateRequest(chatSchema), asyncHandler(async (req: Request, res: Response) => {
    const { licenseKey, messages, options } = req.body;

    const result = await AIService.chat(licenseKey, messages, options);

    if (!result.success) {
        return res.status(400).json({
            success: false,
            error: result.error,
        });
    }

    res.json({
        success: true,
        content: result.content,
        tokensUsed: result.tokensUsed,
    });
}));

export default router;
