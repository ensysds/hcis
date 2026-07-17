import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

const root = new URL("../", import.meta.url);

async function render() {
  const workerUrl = new URL("../dist/server/index.js", import.meta.url);
  workerUrl.searchParams.set("test", `${process.pid}-${Date.now()}`);
  const { default: worker } = await import(workerUrl.href);
  return worker.fetch(new Request("http://localhost/", { headers: { accept: "text/html" } }), {
    ASSETS: { fetch: async () => new Response("Not found", { status: 404 }) },
  }, { waitUntil() {}, passThroughOnException() {} });
}

test("server-renders Core ESS", async () => {
  const response = await render();
  assert.equal(response.status, 200);
  const html = await response.text();
  assert.match(html, /Core — Employee Self Service/i);
  assert.match(html, /Menyiapkan Core/i);
  assert.doesNotMatch(html, /codex-preview|SkeletonPreview/i);
});

test("ships login, session, and logout endpoints", async () => {
  const [login, session, logout] = await Promise.all([
    readFile(new URL("app/api/auth/login/route.ts", root), "utf8"),
    readFile(new URL("app/api/auth/session/route.ts", root), "utf8"),
    readFile(new URL("app/api/auth/logout/route.ts", root), "utf8"),
  ]);
  assert.match(login, /httpOnly:\s*true/);
  assert.match(login, /sameSite:\s*"lax"/);
  assert.match(session, /core_session/);
  assert.match(logout, /cookieStore\.delete\("core_session"\)/);
});

test("keeps the HCIS integration contract in the project", async () => {
  const contract = await readFile(new URL("INTEGRATION-HCIS.md", root), "utf8");
  assert.match(contract, /HCIS tetap menjadi sumber data utama/);
  assert.match(contract, /Idempotency-Key/);
  assert.match(contract, /\/me\/attendance\/check-in/);
});
