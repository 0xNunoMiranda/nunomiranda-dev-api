export class ApiError extends Error {
  statusCode: number;
  code: string;
  details?: unknown;

  constructor(statusCode: number, code: string, message: string, details?: unknown) {
    super(message);
    this.statusCode = statusCode;
    this.code = code;
    this.details = details;
  }
}

export const createNotFound = (message = 'Resource not found') =>
  new ApiError(404, 'not_found', message);

export const createUnauthorized = (message = 'Unauthorized') =>
  new ApiError(401, 'unauthorized', message);

export const createForbidden = (message = 'Forbidden') =>
  new ApiError(403, 'forbidden', message);

export const createBadRequest = (message = 'Bad request') =>
  new ApiError(400, 'bad_request', message);
