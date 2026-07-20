import { cookies } from "next/headers";
import { NextResponse } from "next/server";
import { coreSessionCookieOptions, CORE_SESSION_COOKIE } from "../../../lib/core-session";
import {
  DEMO_CORE_TOKEN,
  demoCoreUser,
  isDemoCoreCredential,
  isDemoHcisMode,
} from "../../../lib/demo-hcis";

const HCIS_API_URL = process.env.HCIS_API_URL?.replace(/\/$/, "");

export async function POST(request: Request) {
  const input = await request.json().catch(() => ({}));

  if (isDemoHcisMode()) {
    if (!isDemoCoreCredential(input.login, input.password)) {
      return NextResponse.json({ message: "Akun demo Core atau kata sandi tidak sesuai." }, { status: 422 });
    }

    const cookieStore = await cookies();
    cookieStore.set(CORE_SESSION_COOKIE, DEMO_CORE_TOKEN, coreSessionCookieOptions());
    return NextResponse.json({ user: demoCoreUser });
  }

  let response: Response;
  try {
    response = await fetch(`${HCIS_API_URL}/auth/login`, {
      method: "POST",
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      body: JSON.stringify({ login: input.login, password: input.password, device_name: "Core Web" }),
      cache: "no-store",
      signal: AbortSignal.timeout(5000),
    });
  } catch {
    return NextResponse.json({ message: "HCIS belum dapat dihubungi." }, { status: 503 });
  }

  const payload = await response.json().catch(() => ({ message: "Layanan login Core belum dapat dihubungi." }));

  if (!response.ok || !payload.token) {
    const status = response.status >= 500 ? 503 : response.status || 502;
    return NextResponse.json({ message: payload.message ?? "Login gagal." }, { status });
  }

  const cookieStore = await cookies();
  cookieStore.set(CORE_SESSION_COOKIE, payload.token, coreSessionCookieOptions());

  return NextResponse.json({ user: payload.user });
}
