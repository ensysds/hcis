import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Mobile Preview",
  description: "Preview Core pada viewport iPhone 17 Pro Max.",
};

export default function MobilePreviewPage() {
  return (
    <main className="mobile-preview-page">
      <style>{mobilePreviewCss}</style>
      <iframe
        className="mobile-preview-screen"
        src="/"
        title="Core Employee Portal at iPhone 17 Pro Max viewport"
      />
    </main>
  );
}

const mobilePreviewCss = `
.mobile-preview-page {
  min-height: 100vh;
  margin: 0;
  display: grid;
  place-items: start center;
  overflow-x: auto;
  background: #f4f8fd;
}

.mobile-preview-screen {
  display: block;
  width: 440px;
  height: 956px;
  border: 0;
  background: #f4f8fd;
}
`;
