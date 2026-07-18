"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useAuth } from "./AuthProvider";
import { LogOut } from "lucide-react";

const links = [
  ["Overview", "/"],
  ["Clients", "/clients"],
  ["Products", "/products"],
  ["Orders & revenue", "/orders"],
  ["Listing optimizer", "/listing-optimizer"],
  ["Keywords & rankings", "/keywords"],
  ["Competitors", "/competitors"],
  ["PPC intelligence", "/advertising"],
  ["Tasks & approvals", "/tasks"],
  ["Experiments", "/experiments"],
  ["Reports", "/reports"],
  ["Alerts", "/alerts"],
  ["Integrations", "/integrations"],
  ["Settings", "/settings"],
] as const;

export function Sidebar() {
  const pathname = usePathname();
  const router = useRouter();
  const { user, organization, logout } = useAuth();
  const initials = user?.name.split(" ").map((part) => part[0]).join("").slice(0, 2).toUpperCase() ?? "OS";

  return (
    <aside className="sidebar">
      <div className="brand"><span className="brandMark">O</span><div><strong>OopSeller</strong><small>{organization?.name ?? "Agency OS"}</small></div></div>
      <nav>{links.map(([label, href]) => <Link className={pathname === href ? "active" : ""} href={href} key={href}><span className="navDot" />{label}</Link>)}</nav>
      <div className="sidebarFooter flex justify-between items-center w-full">
        <div className="flex items-center gap-3 overflow-hidden">
          <div className="avatar shrink-0">{initials}</div>
          <div className="sidebarIdentity flex flex-col truncate">
            <strong className="truncate">{user?.name}</strong>
            <small className="truncate opacity-70">{user?.email}</small>
          </div>
        </div>
        <button 
          className="logoutButton shrink-0 ml-auto p-2 hover:bg-white/10 rounded-lg transition-colors flex items-center justify-center" 
          onClick={async () => { await logout(); router.replace("/login"); }} 
          title="Log out"
          aria-label="Log out"
        >
          <LogOut size={18} />
        </button>
      </div>
    </aside>
  );
}
