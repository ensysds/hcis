import type { Metadata } from "next";
import { CoreApp } from "./CoreApp";

export const metadata: Metadata = {
  title: "Core — Employee Portal",
  description: "Portal karyawan yang terintegrasi langsung dengan HCIS.",
};

export default function Home() {
  return <CoreApp />;
}
