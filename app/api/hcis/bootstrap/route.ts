import { cookies } from "next/headers";
import { NextResponse } from "next/server";

const HCIS_API_URL = process.env.HCIS_API_URL ?? "https://hcis.ensys.id/api/core/v1";

export async function GET() {
  const cookieStore = await cookies();
  const token = cookieStore.get("core_session")?.value;
  if (!token) return NextResponse.json({ message: "Belum login." }, { status: 401 });

  try {
    const response = await fetch(`${HCIS_API_URL}/me/bootstrap`, {
      headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
      cache: "no-store",
      signal: AbortSignal.timeout(3000),
    });
    const payload = await response.json().catch(() => ({ message: "Respons HCIS tidak valid." }));
    if (!response.ok) return NextResponse.json(payload, { status: response.status });
    return NextResponse.json(payload);
  } catch {
    return NextResponse.json({ message: "HCIS belum dapat dihubungi." }, { status: 503 });
  }
}
