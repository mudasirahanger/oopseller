"use client";

import { AppShell } from "@/components/AppShell";
import { EmptyState } from "@/components/EmptyState";
import { PageHeader } from "@/components/PageHeader";
import { StatusPill } from "@/components/StatusPill";
import { apiFetch, ApiError } from "@/lib/api";
import type { Client, Order, OrdersSummary, Paginated } from "@/lib/types";
import { useCallback, useEffect, useMemo, useState } from "react";

const PLATFORM_COLORS: Record<string, string> = {
  amazon: "var(--series-1)",
  flipkart: "var(--series-2)",
  meesho: "var(--series-3)",
  snapdeal: "var(--series-4)",
};

const RANGES: Array<[string, number]> = [["7D", 7], ["30D", 30], ["90D", 90]];

function money(value: number, currency = "INR") {
  return new Intl.NumberFormat("en-IN", { style: "currency", currency, maximumFractionDigits: 0 }).format(value);
}

function RevenueChart({ points }: { points: Array<{ date: string; revenue: number }> }) {
  const width = 720;
  const height = 220;
  const pad = { top: 16, right: 16, bottom: 26, left: 52 };
  const plotW = width - pad.left - pad.right;
  const plotH = height - pad.top - pad.bottom;
  const max = Math.max(1, ...points.map((p) => p.revenue));
  const stepX = points.length > 1 ? plotW / (points.length - 1) : 0;
  const x = (i: number) => pad.left + (points.length > 1 ? i * stepX : plotW / 2);
  const y = (v: number) => pad.top + plotH - (v / max) * plotH;
  const line = points.map((p, i) => `${i === 0 ? "M" : "L"}${x(i).toFixed(1)},${y(p.revenue).toFixed(1)}`).join(" ");
  const area = `${line} L${x(points.length - 1).toFixed(1)},${(pad.top + plotH).toFixed(1)} L${x(0).toFixed(1)},${(pad.top + plotH).toFixed(1)} Z`;
  const ticks = [0, 0.5, 1].map((f) => ({ v: max * f, yy: y(max * f) }));

  return (
    <svg className="chartSvg" viewBox={`0 0 ${width} ${height}`} role="img" aria-label="Revenue over time">
      {ticks.map((t, i) => <g key={i}><line className="gridLine" x1={pad.left} x2={width - pad.right} y1={t.yy} y2={t.yy} /><text className="axisText" x={pad.left - 8} y={t.yy + 4} textAnchor="end">{money(t.v).replace("₹", "₹")}</text></g>)}
      <path d={area} className="chartArea" />
      <path d={line} className="chartLine" />
      {points.map((p, i) => <circle key={i} cx={x(i)} cy={y(p.revenue)} r={points.length <= 31 ? 3 : 0} className="chartDot"><title>{`${p.date}: ${money(p.revenue)}`}</title></circle>)}
      {points.length > 0 && <><text className="axisText" x={pad.left} y={height - 8} textAnchor="start">{points[0].date}</text><text className="axisText" x={width - pad.right} y={height - 8} textAnchor="end">{points[points.length - 1].date}</text></>}
    </svg>
  );
}

export default function OrdersPage() {
  const [summary, setSummary] = useState<OrdersSummary | null>(null);
  const [orders, setOrders] = useState<Order[]>([]);
  const [clients, setClients] = useState<Client[]>([]);
  const [days, setDays] = useState(30);
  const [clientId, setClientId] = useState("");
  const [platform, setPlatform] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const range = useMemo(() => {
    const to = new Date();
    const from = new Date();
    from.setDate(to.getDate() - (days - 1));
    return { from: from.toISOString().slice(0, 10), to: to.toISOString().slice(0, 10) };
  }, [days]);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ from: range.from, to: range.to });
      if (clientId) params.set("client_id", clientId);
      if (platform) params.set("platform", platform);
      const listParams = new URLSearchParams({ per_page: "25" });
      if (clientId) listParams.set("client_id", clientId);
      if (platform) listParams.set("platform", platform);
      const [summaryResponse, orderResponse, clientResponse] = await Promise.all([
        apiFetch<{ data: OrdersSummary }>(`/orders/summary?${params.toString()}`),
        apiFetch<Paginated<Order>>(`/orders?${listParams.toString()}`),
        apiFetch<Paginated<Client>>("/clients?per_page=100"),
      ]);
      setSummary(summaryResponse.data); setOrders(orderResponse.data); setClients(clientResponse.data); setError("");
    } catch (exception) { setError(exception instanceof ApiError ? exception.message : "Unable to load orders."); }
    finally { setLoading(false); }
  }, [range, clientId, platform]);

  useEffect(() => { void load(); }, [load]);

  const totals = summary?.totals;
  const maxPlatformRevenue = Math.max(1, ...(summary?.by_platform ?? []).map((p) => Number(p.revenue)));
  const chartPoints = (summary?.by_day ?? []).map((d) => ({ date: d.date.slice(5), revenue: Number(d.revenue) }));

  return <AppShell><main className="main vizRoot"><PageHeader eyebrow="Revenue intelligence" title="Orders & revenue" description="Unified order and revenue view across every connected sales channel." actions={<div className="rangeGroup">{RANGES.map(([label, value]) => <button key={value} className={days === value ? "rangeButton active" : "rangeButton"} onClick={() => setDays(value)}>{label}</button>)}</div>} />
    <div className="filterRow"><select className="compactSelect" value={clientId} onChange={(e) => setClientId(e.target.value)}><option value="">All clients</option>{clients.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}</select><select className="compactSelect" value={platform} onChange={(e) => setPlatform(e.target.value)}><option value="">All platforms</option><option value="amazon">Amazon</option><option value="flipkart">Flipkart</option><option value="meesho">Meesho</option><option value="snapdeal">Snapdeal</option></select><span className="rangeHint">{range.from} → {range.to}</span></div>
    {error && <div className="errorBox">{error}</div>}
    {loading ? <div className="panel loadingPanel">Loading revenue…</div> : <>
      <div className="summaryStrip"><div><span>Revenue</span><strong>{money(totals?.revenue ?? 0)}</strong></div><div><span>Orders</span><strong>{(totals?.orders ?? 0).toLocaleString("en-IN")}</strong></div><div><span>Units</span><strong>{(totals?.units ?? 0).toLocaleString("en-IN")}</strong></div><div><span>Avg order value</span><strong>{money(totals?.average_order_value ?? 0)}</strong></div></div>
      {(totals?.orders ?? 0) === 0 ? <section className="panel"><EmptyState title="No revenue in this window" description="Connect a sales channel and run an order sync from Integrations. Amazon, Flipkart, Meesho, and Snapdeal orders flow in here." /></section> : <>
        <section className="panel"><div className="panelHeader"><div><h2>Revenue over time</h2><p>Confirmed, shipped, and delivered orders by day</p></div>{(totals?.cancelled_or_returned ?? 0) > 0 && <span className="pill">{totals?.cancelled_or_returned} cancelled / returned</span>}</div><RevenueChart points={chartPoints} /></section>
        <div className="ordersTwoCol">
          <section className="panel"><div className="panelHeader"><div><h2>Revenue by channel</h2><p>Share of revenue per platform</p></div></div><div className="platformBars">{(summary?.by_platform ?? []).map((p) => <div className="platformBar" key={p.platform}><div className="platformBarLabel"><span className="swatch" style={{ background: PLATFORM_COLORS[p.platform] ?? "var(--series-5)" }} />{p.platform}</div><div className="platformBarTrack"><div className="platformBarFill" style={{ width: `${(Number(p.revenue) / maxPlatformRevenue) * 100}%`, background: PLATFORM_COLORS[p.platform] ?? "var(--series-5)" }} /></div><div className="platformBarValue">{money(Number(p.revenue))}<small>{p.orders} orders</small></div></div>)}</div></section>
          <section className="panel"><div className="panelHeader"><div><h2>Top products</h2><p>By revenue in this window</p></div></div>{(summary?.top_products ?? []).length === 0 ? <div className="loadingPanel">No product-level data yet.</div> : <div className="tableWrap"><table><thead><tr><th>Product</th><th>Units</th><th>Revenue</th></tr></thead><tbody>{summary?.top_products.map((p) => <tr key={p.key}><td><strong>{p.name}</strong></td><td>{p.units}</td><td>{money(p.revenue)}</td></tr>)}</tbody></table></div>}</section>
        </div>
        <section className="panel"><div className="panelHeader"><div><h2>Recent orders</h2><p>{orders.length} most recent in this filter</p></div></div>{orders.length === 0 ? <div className="loadingPanel">No orders match the current filter.</div> : <div className="tableWrap"><table><thead><tr><th>Order</th><th>Client</th><th>Channel</th><th>Date</th><th>Units</th><th>Total</th><th>Status</th></tr></thead><tbody>{orders.map((order) => <tr key={order.id}><td><code>{order.external_order_id}</code>{(order.customer_city || order.customer_state) && <small className="cellSubtext">{[order.customer_city, order.customer_state].filter(Boolean).join(", ")}</small>}</td><td>{order.client?.name ?? "—"}</td><td><span className="swatch" style={{ background: PLATFORM_COLORS[order.platform] ?? "var(--series-5)" }} /> {order.platform}</td><td>{new Date(order.order_date).toLocaleDateString("en-IN")}</td><td>{order.units}</td><td>{money(Number(order.total), order.currency)}</td><td><StatusPill value={order.status} /></td></tr>)}</tbody></table></div>}</section>
      </>}
    </>}
  </main></AppShell>;
}
