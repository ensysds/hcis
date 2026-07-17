import { cookies } from "next/headers";
import { NextResponse } from "next/server";

const HCIS_API_URL = process.env.HCIS_API_URL ?? "https://hcis.ensys.id/api/core/v1";

export async function POST() {
  const cookieStore = await cookies();
  const token = cookieStore.get("core_session")?.value;

  if (token) {
    await fetch(`${HCIS_API_URL}/auth/logout`, {
      method: "POST",
      headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
      cache: "no-store",
    }).catch(() => null);
  }

  cookieStore.delete("core_session");
  return NextResponse.json({ message: "Logout berhasil." });
}
