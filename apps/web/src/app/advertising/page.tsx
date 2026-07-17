"use client";

import { AppShell } from "@/components/AppShell";
import { EmptyState } from "@/components/EmptyState";
import { PageHeader } from "@/components/PageHeader";
import { StatusPill } from "@/components/StatusPill";
import { apiFetch, ApiError } from "@/lib/api";
import type { AdvertisingCampaign, AdvertisingSummary, Paginated } from "@/lib/types";
import { useCallback, useEffect, useState } from "react";

export default function AdvertisingPage() {
  const [summary, setSummary] = useState<AdvertisingSummary>({ impressions: 0, clicks: 0, spend: 0, sales: 0, orders: 0, acos: 0, roas: 0 });
  const [campaigns, setCampaigns] = useState<AdvertisingCampaign[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const load = useCallback(async () => { setLoading(true); try { const response = await apiFetch<{ data: { summary: AdvertisingSummary; campaigns: Paginated<AdvertisingCampaign> } }>("/advertising"); setSummary(response.data.summary); setCampaigns(response.data.campaigns.data); setError(""); } catch (exception) { setError(exception instanceof ApiError ? exception.message : "Unable to load advertising data."); } finally { setLoading(false); } }, []);
  useEffect(() => { void load(); }, [load]);
  const money = new Intl.NumberFormat("en-IN", { style: "currency", currency: "INR", maximumFractionDigits: 2 });
  return <AppShell><main className="main"><PageHeader eyebrow="Amazon Ads" title="PPC intelligence" description="This page displays only advertising records already imported into OopSeller. Amazon Ads OAuth and report ingestion are a separate integration from SP-API." />{error && <div className="errorBox">{error}</div>}<section className="summaryStrip"><div><span>Spend</span><strong>{money.format(summary.spend)}</strong></div><div><span>Attributed sales</span><strong>{money.format(summary.sales)}</strong></div><div><span>ACOS</span><strong>{summary.acos.toFixed(2)}%</strong></div><div><span>ROAS</span><strong>{summary.roas.toFixed(2)}</strong></div></section><section className="panel"><div className="panelHeader"><div><h2>Campaigns</h2><p>Reporting period: latest 30 days from stored metrics</p></div></div>{loading ? <div className="loadingPanel">Loading advertising data…</div> : campaigns.length === 0 ? <EmptyState title="No Amazon Ads data" description="No campaign data has been imported. This build does not pretend that SP-API also authorizes Amazon Ads." /> : <div className="tableWrap"><table><thead><tr><th>Campaign</th><th>Ad type</th><th>Targeting</th><th>Budget</th><th>Spend</th><th>Sales</th><th>State</th></tr></thead><tbody>{campaigns.map((campaign) => <tr key={campaign.id}><td><strong>{campaign.name}</strong><small className="cellSubtext">{campaign.campaign_id}</small></td><td>{campaign.ad_type}</td><td>{campaign.targeting_type || "—"}</td><td>{campaign.daily_budget != null ? money.format(Number(campaign.daily_budget)) : "—"}</td><td>{money.format(Number(campaign.spend ?? 0))}</td><td>{money.format(Number(campaign.sales ?? 0))}</td><td><StatusPill value={campaign.state} /></td></tr>)}</tbody></table></div>}</section><div className="infoBox"><strong>Integration boundary</strong><p>Seller Central authorization grants SP-API access. Amazon Ads requires a separate OAuth application, advertiser profiles, and reporting jobs before campaign data can populate this module.</p></div></main></AppShell>;
}
