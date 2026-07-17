import { cookies } from "next/headers";
import { NextResponse } from "next/server";

const HCIS_API_URL = process.env.HCIS_API_URL ?? "https://hcis.ensys.id/api/core/v1";

export async function POST(request: Request) {
  const input = await request.json().catch(() => ({}));
  const response = await fetch(`${HCIS_API_URL}/auth/login`, {
    method: "POST",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body: JSON.stringify({ login: input.login, password: input.password, device_name: "Core Web" }),
    cache: "no-store",
  });
  const payload = await response.json().catch(() => ({ message: "HCIS tidak dapat dihubungi." }));

  if (!response.ok || !payload.token) {
    return NextResponse.json({ message: payload.message ?? "Login gagal." }, { status: response.status || 502 });
  }

  const cookieStore = await cookies();
  cookieStore.set("core_session", payload.token, {
    httpOnly: true,
    secure: true,
    sameSite: "lax",
    maxAge: 60 * 60 * 12,
    path: "/",
  });

  return NextResponse.json({ user: payload.user });
}
