import { cookies } from "next/headers";
import { NextResponse } from "next/server";
import { CORE_SESSION_COOKIE } from "../../../lib/core-session";
import { isDemoCoreToken, isDemoHcisMode } from "../../../lib/demo-hcis";

const HCIS_API_URL = process.env.HCIS_API_URL?.replace(/\/$/, "");

export async function POST(request: Request) {
  const cookieStore = await cookies();
  const token = cookieStore.get(CORE_SESSION_COOKIE)?.value;
  if (!token) return NextResponse.json({ message: "Belum login." }, { status: 401 });

  const input = await request.json().catch(() => ({}));
  if (input.action !== "check-in" && input.action !== "check-out") {
    return NextResponse.json({ message: "Aksi kehadiran tidak valid." }, { status: 422 });
  }

  if (isDemoHcisMode()) {
    if (!isDemoCoreToken(token)) {
      cookieStore.delete(CORE_SESSION_COOKIE);
      return NextResponse.json({ message: "Sesi berakhir." }, { status: 401 });
    }

    return NextResponse.json({
      message: input.action === "check-in" ? "Check-in demo berhasil." : "Check-out demo berhasil.",
      action: input.action,
      recorded_at: new Date().toISOString(),
    });
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
