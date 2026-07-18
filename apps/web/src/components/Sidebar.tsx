"use client";

import Link from "next/link";
import type { Route } from "next";
import { usePathname, useRouter } from "next/navigation";
import { useAuth } from "./AuthProvider";
import {
  LayoutDashboard, Users, Package, ShoppingCart, FileText, Search, Target,
  Megaphone, ListChecks, FlaskConical, BarChart3, Bell, Plug, Settings, LogOut,
  type LucideIcon,
} from "lucide-react";

type NavItem = { label: string; href: Route; icon: LucideIcon };
type NavGroup = { title: string; items: NavItem[] };

const groups: NavGroup[] = [
  {
    title: "Workspace",
    items: [
      { label: "Overview", href: "/", icon: LayoutDashboard },
      { label: "Clients", href: "/clients", icon: Users },
      { label: "Products", href: "/products", icon: Package },
      { label: "Orders & revenue", href: "/orders", icon: ShoppingCart },
    ],
  },
  {
    title: "Intelligence",
    items: [
      { label: "Listing optimizer", href: "/listing-optimizer", icon: FileText },
      { label: "Keywords & rankings", href: "/keywords", icon: Search },
      { label: "Competitors", href: "/competitors", icon: Target },
      { label: "PPC intelligence", href: "/advertising", icon: Megaphone },
    ],
  },
  {
    title: "Operations",
    items: [
      { label: "Tasks & approvals", href: "/tasks", icon: ListChecks },
      { label: "Experiments", href: "/experiments", icon: FlaskConical },
      { label: "Reports", href: "/reports", icon: BarChart3 },
      { label: "Alerts", href: "/alerts", icon: Bell },
    ],
  },
  {
    title: "Setup",
    items: [
      { label: "Integrations", href: "/integrations", icon: Plug },
      { label: "Settings", href: "/settings", icon: Settings },
    ],
  },
];

export function Sidebar() {
  const pathname = usePathname();
  const router = useRouter();
  const { user, organization, logout } = useAuth();
  const initials = user?.name.split(" ").map((part) => part[0]).join("").slice(0, 2).toUpperCase() ?? "OS";

  return (
    <aside className="sidebar">
      <div className="brand"><span className="brandMark">O</span><div><strong>OopSeller</strong><small>{organization?.name ?? "Agency OS"}</small></div></div>
      <nav className="sidebarNav">
        {groups.map((group) => (
          <div className="navGroup" key={group.title}>
            <span className="navGroupTitle">{group.title}</span>
            {group.items.map(({ label, href, icon: Icon }) => {
              const active = pathname === href;
              return (
                <Link className={active ? "navLink active" : "navLink"} href={href} key={href} aria-current={active ? "page" : undefined}>
                  <Icon className="navIcon" size={18} strokeWidth={2} />
                  <span className="navLabel">{label}</span>
                </Link>
              );
            })}
          </div>
        ))}
      </nav>
      <div className="sidebarFooter">
        <div className="avatar">{initials}</div>
        <div className="sidebarIdentity">
          <strong>{user?.name}</strong>
          <small>{user?.email}</small>
        </div>
        <button className="logoutButton" onClick={async () => { await logout(); router.replace("/login"); }} title="Log out" aria-label="Log out">
          <LogOut size={18} />
        </button>
      </div>
    </aside>
  );
}
