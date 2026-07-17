import { cookies } from "next/headers";
import { NextResponse } from "next/server";

const HCIS_API_URL = process.env.HCIS_API_URL ?? "https://hcis.ensys.id/api/core/v1";

export async function GET() {
  const cookieStore = await cookies();
  const token = cookieStore.get("core_session")?.value;
  if (!token) return NextResponse.json({ message: "Belum login." }, { status: 401 });

  let response: Response;
  try {
    response = await fetch(`${HCIS_API_URL}/auth/session`, {
      headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
      cache: "no-store",
      signal: AbortSignal.timeout(1500),
    });
  } catch {
    return NextResponse.json({ message: "Pemeriksaan sesi terlalu lama." }, { status: 503 });
  }

  if (!response.ok) {
    if (response.status === 401 || response.status === 403) {
      cookieStore.delete("core_session");
      return NextResponse.json({ message: "Sesi berakhir." }, { status: 401 });
    }
    return NextResponse.json({ message: "HCIS belum merespons." }, { status: 503 });
  }

  return NextResponse.json(await response.json());
}
