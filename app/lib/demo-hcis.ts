export const DEMO_CORE_TOKEN = "core-demo-session";

export const demoCoreUser = {
  id: 10001,
  name: "Nadia Salsabila",
  email: "demo.core@ensys.id",
  nrp: "10001",
  company: "Ensys",
  department: "Human Capital",
  position: "HCIS Manager",
  roles: ["employee", "manager"],
  modules: [
    "attendance",
    "leave",
    "overtime",
    "payroll",
    "claim",
    "loan",
    "document",
    "bpjs",
    "learning",
    "knowledge",
    "innovation",
    "performance",
  ],
};

export const demoCoreWorkspace = {
  modules: [
    {
      key: "attendance",
      label: "Kehadiran",
      name: "Attendance",
      description: "Absensi harian dan koreksi kehadiran",
      abilities: ["view", "check_in", "check_out"],
    },
    {
      key: "leave",
      label: "Cuti",
      name: "Leave",
      description: "Saldo dan pengajuan cuti",
      abilities: ["view", "create"],
    },
    {
      key: "overtime",
      label: "Lembur",
      name: "Overtime",
      description: "Pengajuan dan persetujuan lembur",
      abilities: ["view", "create", "approve"],
    },
    {
      key: "payroll",
      label: "Payroll",
      name: "Payroll",
      description: "Slip gaji dan ringkasan payroll pribadi",
      abilities: ["view"],
    },
    {
      key: "claim",
      label: "Klaim",
      name: "Claim",
      description: "Reimbursement dan klaim benefit",
      abilities: ["view", "create"],
    },
    {
      key: "loan",
      label: "Pinjaman",
      name: "Loan",
      description: "Plafon dan cicilan pinjaman karyawan",
      abilities: ["view"],
    },
    {
      key: "document",
      label: "Dokumen",
      name: "Document",
      description: "Surat dan formulir karyawan",
      abilities: ["view", "create"],
    },
    {
      key: "bpjs",
      label: "Kesejahteraan",
      name: "Benefit",
      description: "BPJS dan benefit karyawan",
      abilities: ["view"],
    },
    {
      key: "learning",
      label: "Learning",
      name: "Learning",
      description: "Course, assessment, dan sertifikat",
      abilities: ["view", "consume"],
    },
    {
      key: "knowledge",
      label: "Knowledge",
      name: "Knowledge",
      description: "Artikel dan knowledge sharing",
      abilities: ["view", "create"],
    },
    {
      key: "innovation",
      label: "Innovation",
      name: "Innovation",
      description: "Pengajuan dan pemantauan ide",
      abilities: ["view", "create"],
    },
    {
      key: "performance",
      label: "Performance",
      name: "Performance",
      description: "KPI dan appraisal",
      abilities: ["view"],
    },
  ],
  summary: {
    attendance: { check_in_at: "2026-07-20T08:03:00+07:00", check_out_at: null },
    leave_remaining: 9,
    latest_payslip: { period: "Juni 2026", status: "published" },
  },
};

export function isDemoHcisMode() {
  return !process.env.HCIS_API_URL?.trim();
}

export function isDemoCoreToken(token: string | undefined) {
  return token === DEMO_CORE_TOKEN;
}

export function isDemoCoreCredential(login: unknown, password: unknown) {
  const normalizedLogin = String(login ?? "").trim().toLowerCase();
  return (
    password === "core-demo" &&
    (normalizedLogin === demoCoreUser.email ||
      normalizedLogin === demoCoreUser.nrp ||
      normalizedLogin === "manager.core@ensys.id")
  );
}
