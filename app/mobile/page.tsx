import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Mobile Preview",
  description: "Preview Core pada viewport iPhone 17 Pro Max.",
};

export default function MobilePreviewPage() {
  return (
    <main className="mobile-preview-page">
      <style>{mobilePreviewCss}</style>
      <section className="mobile-preview-panel" aria-label="Preview Core mobile">
        <div className="mobile-preview-header">
          <div>
            <span>CORE MOBILE PREVIEW</span>
            <h1>iPhone 17 Pro Max</h1>
          </div>
          <dl>
            <div>
              <dt>Viewport</dt>
              <dd>440 x 956 CSS px</dd>
            </div>
            <div>
              <dt>Display</dt>
              <dd>1320 x 2868 px @3x</dd>
            </div>
            <div>
              <dt>Body</dt>
              <dd>78.0 x 163.4 mm</dd>
            </div>
          </dl>
        </div>

        <div className="mobile-preview-stage">
          <div className="mobile-preview-phone" aria-label="Core in iPhone 17 Pro Max frame">
            <div className="mobile-preview-hardware">
              <span className="mobile-preview-speaker" />
              <span className="mobile-preview-island" />
              <iframe
                className="mobile-preview-screen"
                src="/"
                title="Core Employee Portal mobile viewport"
              />
            </div>
          </div>
        </div>
      </section>
    </main>
  );
}

const mobilePreviewCss = `
.mobile-preview-page {
  min-height: 100vh;
  margin: 0;
  overflow-x: auto;
  background:
    radial-gradient(circle at 12% 16%, rgba(46, 158, 255, 0.22), transparent 30%),
    radial-gradient(circle at 88% 8%, rgba(103, 84, 217, 0.16), transparent 26%),
    linear-gradient(135deg, #071d38 0%, #0a3562 45%, #f4f8fd 45%);
  color: #15233b;
}

.mobile-preview-panel {
  width: max-content;
  min-width: 100%;
  min-height: 100vh;
  display: grid;
  grid-template-rows: auto 1fr;
  gap: 18px;
  padding: 24px;
}

.mobile-preview-header {
  width: min(100%, 1030px);
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 24px;
  margin: 0 auto;
  color: #ffffff;
}

.mobile-preview-header span {
  display: block;
  margin-bottom: 5px;
  color: rgba(255, 255, 255, 0.68);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 1.6px;
}

.mobile-preview-header h1 {
  margin: 0;
  font-size: 28px;
  line-height: 1;
  letter-spacing: -0.8px;
}

.mobile-preview-header dl {
  display: flex;
  gap: 10px;
  margin: 0;
}

.mobile-preview-header dl div {
  min-width: 138px;
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 10px;
  padding: 10px 12px;
  background: rgba(255, 255, 255, 0.08);
}

.mobile-preview-header dt {
  margin: 0 0 3px;
  color: rgba(255, 255, 255, 0.58);
  font-size: 9px;
  font-weight: 750;
  letter-spacing: 1px;
  text-transform: uppercase;
}

.mobile-preview-header dd {
  margin: 0;
  color: #ffffff;
  font-size: 12px;
  font-weight: 700;
  white-space: nowrap;
}

.mobile-preview-stage {
  min-width: 100%;
  display: grid;
  place-items: start center;
  padding: 0 0 42px;
}

.mobile-preview-phone {
  width: 482px;
  height: 998px;
  border-radius: 64px;
  padding: 14px;
  background:
    linear-gradient(145deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0) 38%),
    linear-gradient(145deg, #2c3342, #080b10 54%, #1d2430);
  box-shadow:
    0 30px 80px rgba(7, 29, 56, 0.38),
    inset 0 0 0 1px rgba(255, 255, 255, 0.2),
    inset 0 -18px 36px rgba(0, 0, 0, 0.32);
}

.mobile-preview-hardware {
  position: relative;
  width: 454px;
  height: 970px;
  border-radius: 52px;
  padding: 7px;
  background: #05070a;
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.09);
}

.mobile-preview-speaker {
  position: absolute;
  top: -4px;
  left: 50%;
  z-index: 3;
  width: 86px;
  height: 5px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.12);
  transform: translateX(-50%);
}

.mobile-preview-island {
  position: absolute;
  top: 18px;
  left: 50%;
  z-index: 3;
  width: 112px;
  height: 34px;
  border-radius: 999px;
  background: #07080b;
  box-shadow:
    inset 0 1px 1px rgba(255, 255, 255, 0.1),
    0 1px 0 rgba(255, 255, 255, 0.06);
  pointer-events: none;
}

.mobile-preview-screen {
  display: block;
  width: 440px;
  height: 956px;
  border: 0;
  border-radius: 45px;
  background: #f4f8fd;
}

@media (max-width: 760px) {
  .mobile-preview-panel {
    padding: 18px 14px 28px;
  }

  .mobile-preview-header {
    display: block;
    width: 482px;
  }

  .mobile-preview-header dl {
    display: grid;
    grid-template-columns: 1fr;
    margin-top: 12px;
  }

  .mobile-preview-header dl div {
    min-width: 0;
  }
}
`;
