export const CORE_SESSION_COOKIE = "core_session";

export function coreSessionCookieOptions() {
  return {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax" as const,
    maxAge: 60 * 60 * 12,
    path: "/",
  };
}
