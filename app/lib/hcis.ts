export type HcisRequestOptions = {
  method?: "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
  body?: unknown;
  employeeId?: string;
  idempotencyKey?: string;
  correlationId?: string;
};

export class HcisIntegrationError extends Error {
  constructor(message: string, public status: number, public details?: unknown) {
    super(message);
    this.name = "HcisIntegrationError";
  }
}

/**
 * Server-side adapter for Core → HCIS traffic. Master HR data is never persisted
 * by this app; callers should cache responses only briefly and invalidate them
 * when HCIS webhooks arrive.
 */
export async function hcisRequest<T>(path: string, options: HcisRequestOptions = {}): Promise<T> {
  const baseUrl = process.env.HCIS_API_URL?.replace(/\/$/, "");
  if (!baseUrl) throw new HcisIntegrationError("HCIS_API_URL belum dikonfigurasi", 503);

  const headers = new Headers({ Accept: "application/json", "Content-Type": "application/json" });
  if (process.env.HCIS_SERVICE_TOKEN) headers.set("Authorization", `Bearer ${process.env.HCIS_SERVICE_TOKEN}`);
  if (options.employeeId) headers.set("X-Core-Employee-Id", options.employeeId);
  if (options.idempotencyKey) headers.set("Idempotency-Key", options.idempotencyKey);
  headers.set("X-Correlation-Id", options.correlationId ?? crypto.randomUUID());

  const response = await fetch(`${baseUrl}/${path.replace(/^\//, "")}`, {
    method: options.method ?? "GET",
    headers,
    body: options.body === undefined ? undefined : JSON.stringify(options.body),
    cache: "no-store",
  });

  const payload = await response.json().catch(() => null);
  if (!response.ok) throw new HcisIntegrationError("HCIS tidak dapat memproses permintaan", response.status, payload);
  return payload as T;
}
