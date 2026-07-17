import type { Metadata } from "next";
import { CoreApp } from "./CoreApp";

export const metadata: Metadata = {
  title: "Core — Employee Self Service",
  description: "Satu tempat untuk seluruh kebutuhan kerja karyawan, terintegrasi langsung dengan HCIS.",
};

export default function Home() {
  return <CoreApp />;
}
