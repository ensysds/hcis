import type { Metadata } from "next";
import { Geist } from "next/font/google";
import "./globals.css";

const geist = Geist({ variable: "--font-geist", subsets: ["latin"] });

export const metadata: Metadata = {
  title: { default: "Core — Employee Portal", template: "%s · Core" },
  description: "Portal karyawan yang terhubung langsung dengan HCIS.",
  icons: { icon: "/favicon.svg", shortcut: "/favicon.svg" },
  openGraph: {
    title: "Core — Employee Portal",
    description: "Seluruh kebutuhan kerja karyawan, terhubung langsung dengan HCIS.",
    type: "website",
    images: [{ url: "/og.png", width: 1792, height: 921, alt: "Core Employee Portal" }],
  },
  twitter: {
    card: "summary_large_image",
    title: "Core — Employee Portal",
    description: "Seluruh kebutuhan kerja karyawan, terhubung langsung dengan HCIS.",
    images: ["/og.png"],
  },
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return <html lang="id"><body className={geist.variable}>{children}</body></html>;
}
