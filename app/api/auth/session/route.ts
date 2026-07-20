import { cookies } from "next/headers";
import { NextResponse } from "next/server";
import { CORE_SESSION_COOKIE } from "../../../lib/core-session";
import { demoCoreUser, isDemoCoreToken, isDemoHcisMode } from "../../../lib/demo-hcis";

const HCIS_API_URL = process.env.HCIS_API_URL?.replace(/\/$/, "");

export async function GET() {
  const cookieStore = await cookies();
  const token = cookieStore.get(CORE_SESSION_COOKIE)?.value;
  if (!token) return NextResponse.json({ message: "Belum login." }, { status: 401 });

  if (isDemoHcisMode()) {
    if (isDemoCoreToken(token)) return NextResponse.json({ user: demoCoreUser });
    cookieStore.delete(CORE_SESSION_COOKIE);
    return NextResponse.json({ message: "Sesi berakhir." }, { status: 401 });
  }

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
      cookieStore.delete(CORE_SESSION_COOKIE);
      return NextResponse.json({ message: "Sesi berakhir." }, { status: 401 });
    }
    return NextResponse.json({ message: "Layanan sesi Core belum merespons." }, { status: 503 });
  }

  return NextResponse.json(await response.json());
}
