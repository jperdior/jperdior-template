export class ApiError extends Error {
  constructor(
    public status: number,
    public code: string,
    message: string,
    public payload?: unknown,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

export class UnauthorizedError extends ApiError {
  constructor(message = 'Authentication required.') {
    super(401, 'UNAUTHORIZED', message);
    this.name = 'UnauthorizedError';
  }
}

export class ForbiddenError extends ApiError {
  constructor(message = 'Forbidden.') {
    super(403, 'FORBIDDEN', message);
    this.name = 'ForbiddenError';
  }
}

export class ConflictError extends ApiError {
  constructor(message = 'Conflict.') {
    super(409, 'CONFLICT', message);
    this.name = 'ConflictError';
  }
}

export class ValidationError extends ApiError {
  constructor(message = 'Validation failed.', payload?: unknown) {
    super(422, 'VALIDATION', message, payload);
    this.name = 'ValidationError';
  }
}
