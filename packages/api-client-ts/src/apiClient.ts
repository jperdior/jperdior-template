import { ApiError, ConflictError, ForbiddenError, UnauthorizedError, ValidationError } from './errors';

export interface ApiClientConfig {
  baseUrl: string;
  getAccessToken?: () => string | null | Promise<string | null>;
  refresh?:        () => Promise<string | null>;
  onUnauthorized?: () => void;
}

export interface Note {
  id: string;
  ownerId: string;
  title: string;
  body: string;
  createdAt: string;
  updatedAt: string;
}

export interface CurrentUser {
  id: string;
  email: string;
  roles: string[];
  createdAt: string;
}

interface LoginResponse {
  token: string;
  refresh_token: string;
  refresh_token_expiration?: number;
}

export interface ApiClient {
  signUp(req: { email: string; password: string }): Promise<{ id: string }>;
  login(req: { email: string; password: string }): Promise<LoginResponse>;
  refresh(refreshToken: string): Promise<LoginResponse>;
  me(): Promise<CurrentUser>;
  listNotes(params?: { limit?: number; offset?: number }): Promise<{ total: number; notes: Note[] }>;
  getNote(id: string): Promise<Note>;
  createNote(req: { title: string; body: string }): Promise<{ id: string }>;
  updateNote(id: string, req: { title: string; body: string }): Promise<void>;
  deleteNote(id: string): Promise<void>;
}

export function createApiClient(config: ApiClientConfig): ApiClient {
  async function request<T>(
    method: string,
    path: string,
    options: { body?: unknown; auth?: boolean; query?: Record<string, string | number | undefined> } = {},
  ): Promise<T> {
    const url = new URL(path.replace(/^\//, ''), config.baseUrl.replace(/\/?$/, '/'));
    if (options.query) {
      for (const [k, v] of Object.entries(options.query)) {
        if (v !== undefined) url.searchParams.set(k, String(v));
      }
    }

    const headers: Record<string, string> = { 'Content-Type': 'application/json' };
    if (options.auth !== false && config.getAccessToken) {
      const token = await config.getAccessToken();
      if (token) headers.Authorization = `Bearer ${token}`;
    }

    const init: RequestInit = { method, headers };
    if (options.body !== undefined) init.body = JSON.stringify(options.body);

    let response = await fetch(url, init);

    // One automatic refresh attempt on 401 when a refresh function is configured.
    if (response.status === 401 && config.refresh && options.auth !== false) {
      const newToken = await config.refresh().catch(() => null);
      if (newToken) {
        headers.Authorization = `Bearer ${newToken}`;
        response = await fetch(url, init);
      } else {
        config.onUnauthorized?.();
      }
    }

    if (response.status === 204) return undefined as T;

    const payload = await response.json().catch(() => null);

    if (!response.ok) {
      const message = (payload as { message?: string } | null)?.message ?? 'Request failed.';
      switch (response.status) {
        case 401: throw new UnauthorizedError(message);
        case 403: throw new ForbiddenError(message);
        case 409: throw new ConflictError(message);
        case 422: throw new ValidationError(message, payload);
        default:  throw new ApiError(response.status, 'HTTP_ERROR', message, payload);
      }
    }

    return payload as T;
  }

  return {
    signUp:  (req) => request('POST', '/auth/signup', { body: req, auth: false }),
    login:   (req) => request('POST', '/auth/login',  { body: req, auth: false }),
    refresh: (refreshToken) => request('POST', '/auth/refresh', { body: { refresh_token: refreshToken }, auth: false }),
    me:      ()    => request('GET',  '/api/me'),
    listNotes: (params) => request('GET', '/api/notes', { query: { limit: params?.limit, offset: params?.offset } }),
    getNote:   (id) => request('GET',    `/api/notes/${encodeURIComponent(id)}`),
    createNote:(req) => request('POST',  '/api/notes', { body: req }),
    updateNote:(id, req) => request('PATCH', `/api/notes/${encodeURIComponent(id)}`, { body: req }),
    deleteNote:(id) => request('DELETE', `/api/notes/${encodeURIComponent(id)}`),
  };
}
