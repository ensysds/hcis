import { cookies } from "next/headers";
import { NextResponse } from "next/server";
import { CORE_SESSION_COOKIE } from "../../../lib/core-session";
import { isDemoHcisMode } from "../../../lib/demo-hcis";

const HCIS_API_URL = process.env.HCIS_API_URL?.replace(/\/$/, "");

export async function POST() {
  const cookieStore = await cookies();
  const token = cookieStore.get(CORE_SESSION_COOKIE)?.value;

  if (token && !isDemoHcisMode()) {
    await fetch(`${HCIS_API_URL}/auth/logout`, {
      method: "POST",
      headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
      cache: "no-store",
    }).catch(() => null);
  }

  cookieStore.delete(CORE_SESSION_COOKIE);
  return NextResponse.json({ message: "Logout berhasil." });
}
