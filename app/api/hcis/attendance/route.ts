import { cookies } from "next/headers";
import { NextResponse } from "next/server";

const HCIS_API_URL = process.env.HCIS_API_URL ?? "https://hcis.ensys.id/api/core/v1";

export async function POST(request: Request) {
  const cookieStore = await cookies();
  const token = cookieStore.get("core_session")?.value;
  if (!token) return NextResponse.json({ message: "Belum login." }, { status: 401 });

  const input = await request.json().catch(() => ({}));
  if (input.action !== "check-in" && input.action !== "check-out") {
    return NextResponse.json({ message: "Aksi kehadiran tidak valid." }, { status: 422 });
  }

  try {
    const response = await fetch(`${HCIS_API_URL}/me/attendance/${input.action}`, {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
        "Idempotency-Key": request.headers.get("Idempotency-Key") ?? crypto.randomUUID(),
      },
      body: JSON.stringify({ latitude: input.latitude, longitude: input.longitude }),
      cache: "no-store",
      signal: AbortSignal.timeout(5000),
    });
    const payload = await response.json().catch(() => ({ message: "Respons HCIS tidak valid." }));
    return NextResponse.json(payload, { status: response.status });
  } catch {
    return NextResponse.json({ message: "HCIS belum dapat dihubungi." }, { status: 503 });
  }
}
