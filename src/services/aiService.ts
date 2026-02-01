import OpenAI from 'openai';
import { ResultSetHeader, RowDataPacket } from 'mysql2';
import pool from '../db';
import { LicenseService, ModulesConfig } from './licenseService';
import logger from '../logger';
import config from '../config';

// Lazy initialization do cliente OpenAI
let openai: OpenAI | null = null;
let openaiInitialized = false;

function getOpenAI(): OpenAI | null {
    if (!openaiInitialized) {
        openaiInitialized = true;
        if (config.OPENAI_API_KEY) {
            openai = new OpenAI({
                apiKey: config.OPENAI_API_KEY,
            });
            logger.info('OpenAI client initialized');
        } else {
            logger.warn('OpenAI API key not configured - AI features will be limited');
        }
    }
    return openai;
}

export interface ChatMessage {
    role: 'system' | 'user' | 'assistant';
    content: string;
}

export interface BotConfig {
    type: 'faq' | 'ai' | 'hybrid';
    systemPrompt?: string;
    faqs?: Array<{ question: string; answer: string; keywords?: string[] }>;
    features: ('info' | 'bookings' | 'shop')[];
    businessName?: string;
    businessInfo?: string;
}

export interface GenerationResult {
    success: boolean;
    content?: any;
    tokensUsed?: number;
    error?: string;
}

interface ConversationRow extends RowDataPacket {
    id: number;
    license_id: number;
    session_id: string;
    channel: string;
    context: string | null;
    status: string;
}

export class AIService {
    /**
     * Chat completion com controlo de créditos
     */
    static async chat(
        licenseKey: string,
        messages: ChatMessage[],
        options?: {
            model?: string;
            maxTokens?: number;
            temperature?: number;
        }
    ): Promise<GenerationResult> {
        // Verificar se OpenAI está configurado
        const client = getOpenAI();
        if (!client) {
            return { success: false, error: 'OpenAI API not configured' };
        }

        // Validar licença
        const validation = await LicenseService.validateLicense(licenseKey, 'bot_widget');
        if (!validation.valid) {
            return { success: false, error: validation.error };
        }

        // Verificar créditos
        const credits = await LicenseService.checkCredits(licenseKey, 'ai_message');
        if (!credits.hasCredits) {
            return { success: false, error: 'No AI credits remaining this month' };
        }

        try {
            const model = options?.model || 'gpt-4o-mini';
            
            const completion = await client.chat.completions.create({
                model,
                messages,
                max_tokens: options?.maxTokens || 500,
                temperature: options?.temperature || 0.7,
            });

            const tokensUsed = completion.usage?.total_tokens || 0;
            const content = completion.choices[0]?.message?.content || '';

            // Consumir créditos
            await LicenseService.consumeCredits(licenseKey, 'ai_message', 1, {
                tokens: tokensUsed,
                model,
            });

            return {
                success: true,
                content,
                tokensUsed,
            };
        } catch (error: any) {
            logger.error({ error }, 'OpenAI chat error');
            return { success: false, error: error.message || 'AI chat failed' };
        }
    }

    /**
     * Handle bot message (FAQ, AI ou híbrido)
     */
    static async handleBotMessage(
        licenseKey: string,
        sessionId: string,
        userMessage: string,
        config: BotConfig
    ): Promise<GenerationResult> {
        // Validar licença
        const validation = await LicenseService.validateLicense(licenseKey, 'bot_widget');
        if (!validation.valid) {
            return { success: false, error: validation.error };
        }

        const license = validation.license!;

        // Get or create conversation
        const conversation = await this.getOrCreateConversation(license.id, sessionId);

        // Save user message
        await this.saveMessage(conversation.id, 'user', userMessage);

        let response: string;

        switch (config.type) {
            case 'faq':
                response = this.handleFaqBot(userMessage, config);
                break;
            case 'ai':
                const aiResult = await this.handleAiBot(licenseKey, userMessage, config, conversation.id);
                if (!aiResult.success) {
                    return aiResult;
                }
                response = aiResult.content;
                break;
            case 'hybrid':
                // Try FAQ first
                response = this.handleFaqBot(userMessage, config);
                
                // If no FAQ match, use AI
                if (response.includes('não consegui encontrar') || response.includes('poderia reformular')) {
                    const credits = await LicenseService.checkCredits(licenseKey, 'ai_message');
                    if (credits.hasCredits) {
                        const aiResult = await this.handleAiBot(licenseKey, userMessage, config, conversation.id);
                        if (aiResult.success) {
                            response = aiResult.content;
                        }
                    }
                }
                break;
            default:
                response = 'Tipo de bot não suportado.';
        }

        // Save assistant response
        await this.saveMessage(conversation.id, 'assistant', response);

        return {
            success: true,
            content: response,
        };
    }

    /**
     * Handle FAQ bot (sem AI)
     */
    private static handleFaqBot(userMessage: string, config: BotConfig): string {
        if (!config.faqs || config.faqs.length === 0) {
            return 'Olá! Como posso ajudar?';
        }

        const messageLower = userMessage.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        
        // Search for matching FAQ
        for (const faq of config.faqs) {
            const questionLower = faq.question.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            
            // Check keywords first
            if (faq.keywords) {
                for (const keyword of faq.keywords) {
                    const keywordLower = keyword.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                    if (messageLower.includes(keywordLower)) {
                        return faq.answer;
                    }
                }
            }
            
            // Check question similarity
            const words = questionLower.split(/\s+/).filter(w => w.length > 3);
            const matchCount = words.filter(word => messageLower.includes(word)).length;
            
            if (matchCount >= Math.ceil(words.length * 0.5)) {
                return faq.answer;
            }
        }

        return 'Desculpa, não consegui encontrar uma resposta para a tua pergunta. Poderia reformular ou contactar-nos diretamente?';
    }

    /**
     * Handle AI bot
     */
    private static async handleAiBot(
        licenseKey: string,
        userMessage: string,
        config: BotConfig,
        conversationId: number
    ): Promise<GenerationResult> {
        // Get conversation history
        const history = await this.getConversationHistory(conversationId, 10);

        // Build system prompt
        let systemPrompt = config.systemPrompt || this.buildDefaultSystemPrompt(config);

        const messages: ChatMessage[] = [
            { role: 'system', content: systemPrompt },
            ...history,
            { role: 'user', content: userMessage },
        ];

        return this.chat(licenseKey, messages, {
            maxTokens: 300,
            temperature: 0.7,
        });
    }

    /**
     * Build default system prompt
     */
    private static buildDefaultSystemPrompt(config: BotConfig): string {
        let prompt = `És o assistente virtual`;
        
        if (config.businessName) {
            prompt += ` de ${config.businessName}`;
        }
        
        prompt += `. Sê sempre simpático, profissional e responde em português de Portugal.`;
        
        if (config.businessInfo) {
            prompt += `\n\nInformações do negócio:\n${config.businessInfo}`;
        }

        if (config.features.includes('bookings')) {
            prompt += `\n\nPodes ajudar com marcações e agendamentos. Recolhe informações como nome, contacto, serviço pretendido e data/hora preferida.`;
        }

        if (config.features.includes('shop')) {
            prompt += `\n\nPodes ajudar com questões sobre produtos e compras.`;
        }

        prompt += `\n\nSe não souberes algo, sugere que o cliente contacte diretamente o negócio.`;

        return prompt;
    }

    /**
     * Get or create conversation
     */
    private static async getOrCreateConversation(
        licenseId: number,
        sessionId: string
    ): Promise<{ id: number; context: any }> {
        const [rows] = await pool.query<ConversationRow[]>(
            `SELECT * FROM bot_conversations WHERE license_id = ? AND session_id = ? AND status = 'active' LIMIT 1`,
            [licenseId, sessionId]
        );

        if (rows.length > 0) {
            return {
                id: rows[0].id,
                context: rows[0].context ? JSON.parse(rows[0].context) : {},
            };
        }

        const [result] = await pool.query<ResultSetHeader>(
            `INSERT INTO bot_conversations (license_id, session_id, channel, context) VALUES (?, ?, 'widget', '{}')`,
            [licenseId, sessionId]
        );

        return {
            id: result.insertId,
            context: {},
        };
    }

    /**
     * Save message to conversation
     */
    private static async saveMessage(
        conversationId: number,
        role: 'user' | 'assistant' | 'system',
        content: string,
        tokensUsed: number = 0
    ): Promise<void> {
        await pool.query(
            `INSERT INTO bot_messages (conversation_id, role, content, tokens_used) VALUES (?, ?, ?, ?)`,
            [conversationId, role, content, tokensUsed]
        );
    }

    /**
     * Get conversation history
     */
    private static async getConversationHistory(
        conversationId: number,
        limit: number = 10
    ): Promise<ChatMessage[]> {
        const [rows] = await pool.query<RowDataPacket[]>(
            `SELECT role, content FROM bot_messages WHERE conversation_id = ? ORDER BY created_at DESC LIMIT ?`,
            [conversationId, limit]
        );

        return rows.reverse().map(row => ({
            role: row.role as 'user' | 'assistant' | 'system',
            content: row.content,
        }));
    }

    /**
     * Generate website content with AI
     */
    static async generateSite(
        licenseKey: string,
        businessInfo: {
            name: string;
            description?: string;
            services?: string[];
            phone?: string;
            email?: string;
            address?: string;
            style?: string;
        }
    ): Promise<GenerationResult> {
        // Verificar se OpenAI está configurado
        const client = getOpenAI();
        if (!client) {
            return { success: false, error: 'OpenAI API not configured' };
        }

        const validation = await LicenseService.validateLicense(licenseKey, 'static_site');
        if (!validation.valid) {
            return { success: false, error: validation.error };
        }

        const credits = await LicenseService.checkCredits(licenseKey, 'ai_generation');
        if (credits.remaining < 5) {
            return { success: false, error: 'Insufficient credits for site generation (requires 5 credits)' };
        }

        const prompt = `Gera conteúdo completo para um website profissional em português de Portugal.

Informações do negócio:
- Nome: ${businessInfo.name}
- Descrição: ${businessInfo.description || 'Não fornecida'}
- Serviços: ${businessInfo.services?.join(', ') || 'Não especificados'}
- Telefone: ${businessInfo.phone || 'Não fornecido'}
- Email: ${businessInfo.email || 'Não fornecido'}
- Morada: ${businessInfo.address || 'Não fornecida'}
- Estilo: ${businessInfo.style || 'moderno e profissional'}

Retorna um JSON com esta estrutura:
{
  "hero": {
    "headline": "Título principal chamativo",
    "subheadline": "Subtítulo descritivo",
    "cta": "Texto do botão principal"
  },
  "about": {
    "title": "Sobre Nós",
    "content": "2-3 parágrafos sobre o negócio"
  },
  "services": [
    { "name": "Nome do serviço", "description": "Descrição breve" }
  ],
  "testimonials": [
    { "name": "Nome", "content": "Testemunho fictício mas realista" }
  ],
  "cta": {
    "title": "Chamada à ação final",
    "subtitle": "Texto motivador",
    "button": "Texto do botão"
  },
  "footer": {
    "tagline": "Slogan curto"
  }
}

Responde APENAS com o JSON válido, sem explicações adicionais.`;

        try {
            const completion = await client.chat.completions.create({
                model: 'gpt-4o-mini',
                messages: [
                    { role: 'system', content: 'És um copywriter profissional especializado em websites. Responde sempre em português de Portugal. Retorna apenas JSON válido.' },
                    { role: 'user', content: prompt },
                ],
                max_tokens: 2000,
                temperature: 0.8,
            });

            const tokensUsed = completion.usage?.total_tokens || 0;
            const contentStr = completion.choices[0]?.message?.content || '';

            // Parse JSON
            let content;
            try {
                // Remove markdown code blocks if present
                const cleanJson = contentStr.replace(/```json\n?|\n?```/g, '').trim();
                content = JSON.parse(cleanJson);
            } catch {
                return { success: false, error: 'Failed to parse generated content' };
            }

            // Consume 5 credits
            await LicenseService.consumeCredits(licenseKey, 'ai_generation', 5, {
                tokens: tokensUsed,
                type: 'site_generation',
            });

            // Log generation
            const license = await LicenseService.getLicenseByKey(licenseKey);
            if (license) {
                await pool.query(
                    `INSERT INTO ai_generations (license_id, generation_type, prompt, result, tokens_used, status) VALUES (?, 'site', ?, ?, ?, 'completed')`,
                    [license.id, prompt.substring(0, 500), JSON.stringify(content), tokensUsed]
                );
            }

            return {
                success: true,
                content,
                tokensUsed,
            };
        } catch (error: any) {
            logger.error({ error }, 'Site generation error');
            return { success: false, error: error.message || 'Site generation failed' };
        }
    }

    /**
     * Generate FAQs with AI
     */
    static async generateFaqs(
        licenseKey: string,
        businessInfo: {
            name: string;
            description?: string;
            services?: string[];
        },
        count: number = 10
    ): Promise<GenerationResult> {
        // Verificar se OpenAI está configurado
        const client = getOpenAI();
        if (!client) {
            return { success: false, error: 'OpenAI API not configured' };
        }

        const validation = await LicenseService.validateLicense(licenseKey, 'bot_widget');
        if (!validation.valid) {
            return { success: false, error: validation.error };
        }

        const credits = await LicenseService.checkCredits(licenseKey, 'ai_generation');
        if (credits.remaining < 3) {
            return { success: false, error: 'Insufficient credits for FAQ generation (requires 3 credits)' };
        }

        const prompt = `Gera ${count} perguntas frequentes (FAQs) para ${businessInfo.name}.

Informações:
- Descrição: ${businessInfo.description || 'Não fornecida'}
- Serviços: ${businessInfo.services?.join(', ') || 'Não especificados'}

Retorna um JSON array com esta estrutura:
[
  {
    "question": "Pergunta frequente",
    "answer": "Resposta completa e útil",
    "keywords": ["palavra1", "palavra2"]
  }
]

Inclui perguntas sobre:
- Horários e localização
- Preços e pagamentos
- Serviços oferecidos
- Marcações e cancelamentos
- Dúvidas gerais

Responde APENAS com o JSON array válido.`;

        try {
            const completion = await client.chat.completions.create({
                model: 'gpt-4o-mini',
                messages: [
                    { role: 'system', content: 'És um especialista em FAQ e atendimento ao cliente. Responde em português de Portugal. Retorna apenas JSON válido.' },
                    { role: 'user', content: prompt },
                ],
                max_tokens: 1500,
                temperature: 0.7,
            });

            const tokensUsed = completion.usage?.total_tokens || 0;
            const contentStr = completion.choices[0]?.message?.content || '';

            let content;
            try {
                const cleanJson = contentStr.replace(/```json\n?|\n?```/g, '').trim();
                content = JSON.parse(cleanJson);
            } catch {
                return { success: false, error: 'Failed to parse generated FAQs' };
            }

            await LicenseService.consumeCredits(licenseKey, 'ai_generation', 3, {
                tokens: tokensUsed,
                type: 'faq_generation',
            });

            return {
                success: true,
                content,
                tokensUsed,
            };
        } catch (error: any) {
            logger.error({ error }, 'FAQ generation error');
            return { success: false, error: error.message || 'FAQ generation failed' };
        }
    }
}
