import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

const root = new URL("../", import.meta.url);

async function render(path = "/") {
  const workerUrl = new URL("../dist/server/index.js", import.meta.url);
  workerUrl.searchParams.set("test", `${process.pid}-${Date.now()}`);
  const { default: worker } = await import(workerUrl.href);
  return worker.fetch(new Request(new URL(path, "http://localhost/"), { headers: { accept: "text/html" } }), {
    ASSETS: { fetch: async () => new Response("Not found", { status: 404 }) },
  }, { waitUntil() {}, passThroughOnException() {} });
}

test("server-renders Core ESS", async () => {
  const response = await render();
  assert.equal(response.status, 200);
  const html = await response.text();
  assert.match(html, /Core — Employee Portal/i);
  assert.match(html, /Masuk ke Core/i);
  assert.doesNotMatch(html, /Menyiapkan Core/i);
  assert.doesNotMatch(html, /codex-preview|SkeletonPreview/i);
});

test("server-renders the iPhone 17 Pro Max mobile preview", async () => {
  const response = await render("/mobile");
  assert.equal(response.status, 200);
  const html = await response.text();
  assert.match(html, /iPhone 17 Pro Max/i);
  assert.match(html, /440 x 956 CSS px/i);
  assert.match(html, /1320 x 2868 px @3x/i);
});

test("ships login, session, and logout endpoints", async () => {
  const [login, session, logout, cookieHelper] = await Promise.all([
    readFile(new URL("app/api/auth/login/route.ts", root), "utf8"),
    readFile(new URL("app/api/auth/session/route.ts", root), "utf8"),
    readFile(new URL("app/api/auth/logout/route.ts", root), "utf8"),
    readFile(new URL("app/lib/core-session.ts", root), "utf8"),
  ]);
  assert.match(cookieHelper, /httpOnly:\s*true/);
  assert.match(cookieHelper, /sameSite:\s*"lax"/);
  assert.match(cookieHelper, /core_session/);
  assert.match(login, /coreSessionCookieOptions/);
  assert.match(session, /CORE_SESSION_COOKIE/);
  assert.match(session, /AbortSignal\.timeout\(1500\)/);
  assert.match(logout, /cookieStore\.delete\(CORE_SESSION_COOKIE\)/);
});

test("keeps the HCIS integration contract in the project", async () => {
  const contract = await readFile(new URL("INTEGRATION-HCIS.md", root), "utf8");
  assert.match(contract, /HCIS tetap menjadi sumber data utama/);
  assert.match(contract, /Idempotency-Key/);
  assert.match(contract, /\/me\/attendance\/check-in/);
});

test("loads role-based HCIS modules and proxies attendance mutations", async () => {
  const [bootstrap, attendance, app] = await Promise.all([
    readFile(new URL("app/api/hcis/bootstrap/route.ts", root), "utf8"),
    readFile(new URL("app/api/hcis/attendance/route.ts", root), "utf8"),
    readFile(new URL("app/CoreApp.tsx", root), "utf8"),
  ]);
  assert.match(bootstrap, /\/me\/bootstrap/);
  assert.match(attendance, /Idempotency-Key/);
  assert.match(attendance, /\/me\/attendance\/\$\{input\.action\}/);
  assert.match(app, /services\.filter\(\(service\) => moduleKeys\.has\(service\.module\)\)/);
});
