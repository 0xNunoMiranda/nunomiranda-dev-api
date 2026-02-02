import 'express-serve-static-core';

export interface TenantAuthContext {
  tenantId: number;
  scopes: string[];
  apiKeyId: number | null;
}

declare module 'express-serve-static-core' {
  interface Request {
    tenant?: TenantAuthContext;
  }
}
