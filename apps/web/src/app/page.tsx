"use client";

import { AppShell } from "@/components/AppShell";
import { EmptyState } from "@/components/EmptyState";
import { PageHeader } from "@/components/PageHeader";
import { StatCard } from "@/components/StatCard";
import { StatusPill } from "@/components/StatusPill";
import { apiFetch, ApiError } from "@/lib/api";
import type { DashboardData } from "@/lib/types";
import Link from "next/link";
import { useEffect, useState } from "react";

export default function Home() {
  const [data, setData] = useState<DashboardData | null>(null);
  const [error, setError] = useState("");

  useEffect(() => {
    apiFetch<{ data: DashboardData }>("/dashboard").then((response) => setData(response.data)).catch((exception) => setError(exception instanceof ApiError ? exception.message : "Unable to load dashboard."));
  }, []);

  return <AppShell><main className="main"><PageHeader eyebrow={new Intl.DateTimeFormat("en-IN", { dateStyle: "full" }).format(new Date())} title="Agency command center" description="Live data from your client workspaces and connected Amazon seller accounts." actions={<><Link className="secondary buttonLink" href="/reports">Reports</Link><Link className="primary buttonLink" href="/clients">+ Add client</Link></>} />{error && <div className="errorBox">{error}</div>}{!data ? <div className="panel loadingPanel">Loading dashboard…</div> : <><section className="statsGrid">{data.stats.map((stat) => <StatCard key={stat.label} stat={stat} />)}</section><section className="contentGrid"><article className="panel wide"><div className="panelHeader"><div><h2>Client portfolio</h2><p>Current workload and managed catalogue</p></div><Link className="textButton" href="/clients">View all</Link></div>{data.clients.length === 0 ? <EmptyState title="No clients yet" description="Add your first seller client, then connect their Amazon account." /> : <div className="tableWrap"><table><thead><tr><th>Client</th><th>Status</th><th>ASINs</th><th>Open tasks</th></tr></thead><tbody>{data.clients.map((client) => <tr key={client.id}><td><strong>{client.name}</strong></td><td><StatusPill value={client.status} /></td><td>{client.products_count}</td><td>{client.tasks_count}</td></tr>)}</tbody></table></div>}</article><article className="panel"><div className="panelHeader"><div><h2>Rank movement</h2><p>Latest provider observations</p></div></div>{data.rankMovements.length === 0 ? <EmptyState title="No ranking data" description="Create keyword projects and configure a compliant ranking provider." /> : <div className="rankList">{data.rankMovements.map((rank) => <div className="rankRow" key={`${rank.asin}-${rank.keyword}`}><div><strong>{rank.keyword}</strong><small>{rank.asin}</small></div><div className="rankValue"><strong>#{rank.position ?? "—"}</strong><span className={(rank.change ?? 0) >= 0 ? "up" : "down"}>{rank.change == null ? "—" : `${rank.change > 0 ? "+" : ""}${rank.change}`}</span></div></div>)}</div>}</article><article className="panel wide"><div className="panelHeader"><div><h2>Priority work</h2><p>Tasks requiring agency attention</p></div><Link className="textButton" href="/tasks">Open board</Link></div>{data.tasks.length === 0 ? <EmptyState title="No open tasks" description="Your task queue is clear." /> : <div className="taskList">{data.tasks.map((task) => <div className="taskRow" key={task.id}><div className={`priority ${task.priority}`} /><div className="taskMain"><strong>{task.title}</strong><small>{task.client?.name} · {task.assignee?.name ?? "Unassigned"}</small></div><StatusPill value={task.status} /><time>{task.due_at ? new Intl.DateTimeFormat("en-IN", { day: "2-digit", month: "short" }).format(new Date(task.due_at)) : "No date"}</time></div>)}</div>}</article><article className="panel"><div className="panelHeader"><div><h2>Optimization opportunities</h2><p>Listings with audit scores below 80</p></div></div>{data.opportunities.length === 0 ? <EmptyState title="No audit findings" description="Run listing audits to identify optimization opportunities." /> : <div className="opportunityList">{data.opportunities.map((item) => <div className="opportunity" key={item.id}><div className="scoreRing">{item.score}</div><div><strong>{item.listing?.product?.name ?? "Listing"}</strong><small>{item.listing?.product?.asin}</small><p>{item.recommendations[0]}</p></div></div>)}</div>}</article></section></>}</main></AppShell>;
}
