"use client";

import {
  Bell, CalendarDays, Check, ChevronRight, CircleDollarSign, Clock3,
  FileText, Fingerprint, Grid2X2, HandCoins, HeartPulse, Home, LogOut,
  Menu, MessageSquareText, MoreHorizontal, Plane, ReceiptText, Search,
  ShieldCheck, Sparkles, TimerReset, UserRound, UsersRound, WalletCards, X,
} from "lucide-react";
import { useEffect, useMemo, useState } from "react";

type NavKey = "beranda" | "aktivitas" | "layanan" | "persetujuan" | "profil";

const navItems: { key: NavKey; label: string; icon: typeof Home }[] = [
  { key: "beranda", label: "Beranda", icon: Home },
  { key: "aktivitas", label: "Aktivitas", icon: Clock3 },
  { key: "layanan", label: "Layanan", icon: Grid2X2 },
  { key: "persetujuan", label: "Persetujuan", icon: ShieldCheck },
  { key: "profil", label: "Profil", icon: UserRound },
];

const services = [
  { title: "Ajukan cuti", note: "Sisa 9 hari", icon: Plane, tone: "mint" },
  { title: "Koreksi absen", note: "Riwayat kehadiran", icon: TimerReset, tone: "sky" },
  { title: "Ajukan lembur", note: "Klaim jam tambahan", icon: Clock3, tone: "violet" },
  { title: "Slip gaji", note: "Juni 2026 tersedia", icon: ReceiptText, tone: "amber" },
  { title: "Reimbursement", note: "Ajukan penggantian", icon: WalletCards, tone: "rose" },
  { title: "Pinjaman", note: "Cek plafon & cicilan", icon: HandCoins, tone: "teal" },
  { title: "Dokumen", note: "Surat & formulir", icon: FileText, tone: "blue" },
  { title: "Kesejahteraan", note: "BPJS & benefit", icon: HeartPulse, tone: "coral" },
];

const activities = [
  { day: "17", month: "JUL", title: "Kehadiran hari ini", detail: "Masuk 08.03 · Menunggu check-out", status: "Berjalan", kind: "active" },
  { day: "16", month: "JUL", title: "Kehadiran kantor", detail: "08.01 — 17.08 · 9j 07m", status: "Tepat waktu", kind: "success" },
  { day: "15", month: "JUL", title: "Kehadiran kantor", detail: "08.12 — 17.05 · 8j 53m", status: "Terlambat 12m", kind: "warning" },
];

function liveClock() {
  return new Intl.DateTimeFormat("id-ID", { hour: "2-digit", minute: "2-digit", second: "2-digit", hour12: false }).format(new Date()).replaceAll(".", ":");
}

export function CoreApp() {
  const [active, setActive] = useState<NavKey>("beranda");
  const [clock, setClock] = useState(liveClock());
  const [checkedIn, setCheckedIn] = useState(true);
  const [showAttendance, setShowAttendance] = useState(false);
  const [showNotifs, setShowNotifs] = useState(false);
  const [toast, setToast] = useState("");

  useEffect(() => {
    const timer = window.setInterval(() => setClock(liveClock()), 1000);
    return () => window.clearInterval(timer);
  }, []);

  useEffect(() => {
    if (!toast) return;
    const timer = window.setTimeout(() => setToast(""), 2800);
    return () => window.clearTimeout(timer);
  }, [toast]);

  const title = useMemo(() => navItems.find((item) => item.key === active)?.label ?? "Beranda", [active]);

  const finishAttendance = () => {
    setCheckedIn((value) => !value);
    setShowAttendance(false);
    setToast(checkedIn ? "Check-out berhasil dikirim ke HCIS" : "Check-in berhasil dikirim ke HCIS");
  };

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <div className="brand"><span className="brand-mark">c</span><span>core</span></div>
        <div className="workspace-pill"><span className="avatar tiny">AR</span><div><strong>PT Sinar Utama</strong><small>Karyawan</small></div><ChevronRight size={16}/></div>
        <nav className="side-nav">
          <p>RUANG KERJA</p>
          {navItems.map(({ key, label, icon: Icon }) => (
            <button key={key} className={active === key ? "active" : ""} onClick={() => setActive(key)}><Icon size={19}/><span>{label}</span>{key === "persetujuan" && <b>3</b>}</button>
          ))}
        </nav>
        <div className="sidebar-footer">
          <div className="sync-state"><span/><div><strong>Terhubung ke HCIS</strong><small>Data tersinkron otomatis</small></div></div>
          <button><LogOut size={18}/> Keluar</button>
        </div>
      </aside>

      <main>
        <header className="topbar">
          <div className="mobile-brand"><span className="brand-mark">c</span><b>core</b></div>
          <div><span className="eyebrow">EMPLOYEE SELF SERVICE</span><h1>{title}</h1></div>
          <div className="top-actions">
            <label className="search"><Search size={17}/><input aria-label="Cari layanan" placeholder="Cari layanan..."/></label>
            <button className="icon-button notification" aria-label="Notifikasi" onClick={() => setShowNotifs(!showNotifs)}><Bell size={20}/><span/></button>
            <div className="profile-chip"><span className="avatar">AR</span><div><strong>Andi Ramadhan</strong><small>Product Designer</small></div><ChevronRight size={16}/></div>
          </div>
        </header>

        {showNotifs && <div className="notification-panel"><div className="panel-head"><b>Notifikasi</b><button onClick={() => setShowNotifs(false)}><X size={17}/></button></div><article><span className="notif-icon"><Check size={16}/></span><div><b>Cuti disetujui</b><p>Pengajuan 22–23 Juli telah disetujui atasan.</p><small>12 menit lalu</small></div></article><article><span className="notif-icon blue"><ReceiptText size={16}/></span><div><b>Slip gaji tersedia</b><p>Slip gaji Juni 2026 sudah dapat dilihat.</p><small>2 hari lalu</small></div></article></div>}

        <div className="content">
          {active === "beranda" && <Dashboard clock={clock} checkedIn={checkedIn} openAttendance={() => setShowAttendance(true)} openServices={() => setActive("layanan")}/>} 
          {active === "aktivitas" && <ActivityPage/>}
          {active === "layanan" && <ServicesPage onAction={(name) => setToast(`${name} siap digunakan`)}/>} 
          {active === "persetujuan" && <ApprovalsPage onApprove={(name) => setToast(`Pengajuan ${name} disetujui dan dikirim ke HCIS`)}/>} 
          {active === "profil" && <ProfilePage/>}
        </div>
      </main>

      <nav className="bottom-nav">
        {navItems.map(({ key, label, icon: Icon }) => <button key={key} className={active === key ? "active" : ""} onClick={() => setActive(key)}><span><Icon size={20}/>{key === "persetujuan" && <i>3</i>}</span><small>{label}</small></button>)}
      </nav>

      {showAttendance && <div className="modal-backdrop" onMouseDown={() => setShowAttendance(false)}><section className="attendance-modal" onMouseDown={(e) => e.stopPropagation()}><button className="close" onClick={() => setShowAttendance(false)}><X size={19}/></button><span className="modal-icon"><Fingerprint size={30}/></span><small>{checkedIn ? "KONFIRMASI CHECK-OUT" : "KONFIRMASI CHECK-IN"}</small><h2>{clock.slice(0,5)} WIB</h2><p>Gedung Harmoni · Jakarta Pusat</p><div className="location-ok"><Check size={15}/> Lokasi Anda terverifikasi</div><button className="primary wide" onClick={finishAttendance}>{checkedIn ? "Check-out sekarang" : "Check-in sekarang"}</button><em>Aktivitas ini akan langsung tercatat di HCIS</em></section></div>}
      {toast && <div className="toast"><Check size={17}/>{toast}</div>}
    </div>
  );
}

function Dashboard({ clock, checkedIn, openAttendance, openServices }: { clock: string; checkedIn: boolean; openAttendance: () => void; openServices: () => void }) {
  return <>
    <section className="welcome"><div><span className="date-chip"><CalendarDays size={14}/> Jumat, 17 Juli 2026</span><h2>Selamat pagi, Andi <span>👋</span></h2><p>Semoga harimu produktif. Semua kebutuhan kerja ada di sini.</p></div><div className="work-mode"><span/><div><small>MODE KERJA HARI INI</small><b>Work from Office</b></div><ChevronRight size={18}/></div></section>
    <section className="dashboard-grid">
      <article className="attendance-card">
        <div className="attendance-head"><div><span className="live-dot"/> KEHADIRAN HARI INI</div><MoreHorizontal size={20}/></div>
        <div className="clock-row"><div><h3>{clock}</h3><p>Waktu Indonesia Barat</p></div><div className="office-badge">WFO</div></div>
        <div className="time-line"><div className="time-point done"><span><Check size={14}/></span><small>CHECK-IN</small><b>08:03</b></div><div className="line"><span style={{width: checkedIn ? "58%" : "100%"}}/></div><div className={`time-point ${checkedIn ? "" : "done"}`}><span>{checkedIn ? "" : <Check size={14}/>}</span><small>CHECK-OUT</small><b>{checkedIn ? "— —" : "17:06"}</b></div></div>
        <button className="attendance-button" onClick={openAttendance}><Fingerprint size={22}/><span><b>{checkedIn ? "Check-out" : "Check-in"}</b><small>Gedung Harmoni · 12 m dari kantor</small></span><ChevronRight size={20}/></button>
        <div className="attendance-foot"><span><Clock3 size={15}/> Durasi kerja <b>{checkedIn ? "7j 14m" : "9j 03m"}</b></span><span>Shift <b>08:00–17:00</b></span></div>
      </article>

      <div className="stat-column">
        <article className="mini-stat leave"><span><Plane size={21}/></span><div><small>SISA CUTI</small><h3>9 <em>hari</em></h3><p>dari 12 hari tahun ini</p></div><ChevronRight size={18}/></article>
        <article className="mini-stat payroll"><span><CircleDollarSign size={21}/></span><div><small>SLIP GAJI</small><h3>Juni 2026</h3><p><i/> Sudah diterbitkan</p></div><ChevronRight size={18}/></article>
        <article className="mini-stat approval"><span><ShieldCheck size={21}/></span><div><small>PERLU PERSETUJUAN</small><h3>3 <em>pengajuan</em></h3><p>Dari anggota tim Anda</p></div><ChevronRight size={18}/></article>
      </div>
    </section>

    <section className="section-block"><div className="section-title"><div><h2>Akses cepat</h2><p>Layanan yang paling sering Anda gunakan</p></div><button onClick={openServices}>Lihat semua <ChevronRight size={16}/></button></div><div className="service-grid">{services.slice(0,6).map(({title,note,icon:Icon,tone}) => <button className="service-card" key={title}><span className={tone}><Icon size={22}/></span><div><b>{title}</b><small>{note}</small></div><ChevronRight size={17}/></button>)}</div></section>

    <section className="two-column"><article className="panel"><div className="section-title compact"><div><h2>Aktivitas terkini</h2><p>Semua transaksi tersinkron ke HCIS</p></div><button>Lihat riwayat</button></div><div className="activity-list">{activities.map((a) => <div className="activity-item" key={a.day}><div className="calendar-box"><b>{a.day}</b><small>{a.month}</small></div><div><b>{a.title}</b><p>{a.detail}</p></div><span className={`status ${a.kind}`}>{a.status}</span></div>)}</div></article><article className="panel team"><div className="section-title compact"><div><h2>Tim hari ini</h2><p>Ringkasan kehadiran anggota tim</p></div><button><UsersRound size={17}/></button></div><div className="team-chart"><div className="donut"><span><b>8</b><small>Anggota</small></span></div><ul><li><i className="green"/>Hadir <b>6</b></li><li><i className="orange"/>Cuti <b>1</b></li><li><i className="gray"/>Belum hadir <b>1</b></li></ul></div><div className="team-avatars"><span className="avatar peach">NS</span><span className="avatar blue-bg">DP</span><span className="avatar green-bg">AY</span><span className="avatar purple-bg">RF</span><span className="avatar">+4</span><small>6 dari 8 sudah hadir</small></div></article></section>
  </>;
}

function ActivityPage() { return <section className="page-panel"><div className="page-heading"><span className="heading-icon sky"><Clock3/></span><div><h2>Aktivitas saya</h2><p>Riwayat absensi dan pengajuan yang tercatat di HCIS.</p></div></div><div className="filter-row"><button className="selected">Semua</button><button>Kehadiran</button><button>Cuti</button><button>Lembur</button><button>Klaim</button></div><div className="activity-list large">{activities.concat([{day:"14",month:"JUL",title:"Pengajuan cuti tahunan",detail:"22–23 Juli 2026 · 2 hari",status:"Disetujui",kind:"success"},{day:"11",month:"JUL",title:"Klaim transportasi",detail:"Kunjungan klien · Rp175.000",status:"Diproses",kind:"active"}]).map((a,i)=><div className="activity-item" key={i}><div className="calendar-box"><b>{a.day}</b><small>{a.month}</small></div><div><b>{a.title}</b><p>{a.detail}</p></div><span className={`status ${a.kind}`}>{a.status}</span><ChevronRight size={17}/></div>)}</div></section> }

function ServicesPage({onAction}:{onAction:(name:string)=>void}) { return <section className="page-panel"><div className="page-heading"><span className="heading-icon violet"><Sparkles/></span><div><h2>Layanan karyawan</h2><p>Ajukan dan pantau seluruh kebutuhan kerja Anda dari satu tempat.</p></div></div><div className="all-services">{services.map(({title,note,icon:Icon,tone})=><button key={title} onClick={()=>onAction(title)}><span className={tone}><Icon/></span><div><b>{title}</b><p>{note}</p></div><ChevronRight/></button>)}</div></section> }

function ApprovalsPage({onApprove}:{onApprove:(name:string)=>void}) { const requests=[{name:"Nadia Sari",type:"Cuti tahunan",detail:"22–24 Juli · 3 hari",initial:"NS",tone:"peach"},{name:"Dimas Pratama",type:"Lembur",detail:"18 Juli · 3 jam",initial:"DP",tone:"blue-bg"},{name:"Ayu Lestari",type:"Koreksi absensi",detail:"15 Juli · Lupa check-out",initial:"AY",tone:"green-bg"}]; return <section className="page-panel"><div className="page-heading"><span className="heading-icon mint"><ShieldCheck/></span><div><h2>Persetujuan tim</h2><p>Tiga pengajuan membutuhkan keputusan Anda.</p></div></div><div className="approval-list">{requests.map(r=><article key={r.name}><span className={`avatar ${r.tone}`}>{r.initial}</span><div><b>{r.name}</b><h3>{r.type}</h3><p>{r.detail}</p></div><button className="ghost">Detail</button><button className="primary" onClick={()=>onApprove(r.name)}><Check size={16}/> Setujui</button></article>)}</div></section> }

function ProfilePage() { return <section className="page-panel"><div className="profile-hero"><span className="avatar xl">AR</span><div><span>NRP 10428</span><h2>Andi Ramadhan</h2><p>Product Designer · Product & Technology</p></div><button className="ghost">Ubah data</button></div><div className="profile-grid"><article><small>INFORMASI KERJA</small><dl><div><dt>Perusahaan</dt><dd>PT Sinar Utama</dd></div><div><dt>Lokasi kerja</dt><dd>Gedung Harmoni, Jakarta</dd></div><div><dt>Atasan langsung</dt><dd>Raka Firmansyah</dd></div><div><dt>Tanggal bergabung</dt><dd>12 Februari 2021</dd></div></dl></article><article><small>KONTAK & AKUN</small><dl><div><dt>Email kantor</dt><dd>andi.ramadhan@demo.com</dd></div><div><dt>Nomor telepon</dt><dd>+62 812 3456 7890</dd></div><div><dt>Status data</dt><dd><span className="verified"><Check size={13}/> Terverifikasi HCIS</span></dd></div></dl></article></div></section> }
