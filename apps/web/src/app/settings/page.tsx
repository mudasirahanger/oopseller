"use client";

import { AppShell } from "@/components/AppShell";
import { PageHeader } from "@/components/PageHeader";
import { useAuth } from "@/components/AuthProvider";
import { authStorage, apiFetch } from "@/lib/api";
import { useState } from "react";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";

function OrganizationSettings() {
  const { user, organization, hydrate } = useAuth();
  const stored = authStorage();
  const [name, setName] = useState(organization?.name ?? "");
  const [slug, setSlug] = useState(organization?.slug ?? "");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");

  const role = user?.organizations?.find((o) => o.id === organization?.id)?.pivot?.role;
  const isAdmin = role === "owner" || role === "admin" || Boolean(user?.is_platform_admin);

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!organization) return;
    setLoading(true);
    setError("");
    setSuccess("");
    try {
      await apiFetch(`/organizations/${organization.id}`, {
        method: "PUT",
        body: JSON.stringify({ name, slug }),
      });
      await hydrate();
      setSuccess("Organization settings updated successfully.");
    } catch (err) {
      setError(err instanceof Error && err.message ? err.message : "Failed to update organization.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="panel">
      <div className="panelHeader">
        <div>
          <h2>Organization</h2>
          <p>Tenant used for all API requests</p>
        </div>
      </div>
      {isAdmin ? (
        <form onSubmit={handleSave} className="flex flex-col gap-4 mt-4">
          <div className="flex flex-col gap-2">
            <label className="text-sm font-semibold">Name</label>
            <Input value={name} onChange={(e) => setName(e.target.value)} required />
          </div>
          <div className="flex flex-col gap-2">
            <label className="text-sm font-semibold">Slug</label>
            <Input value={slug} onChange={(e) => setSlug(e.target.value)} required />
          </div>
          <dl className="detailList mt-2 border-t pt-4">
            <div><dt>Currency</dt><dd>{organization?.currency ?? "—"}</dd></div>
            <div><dt>Timezone</dt><dd>{organization?.timezone ?? "—"}</dd></div>
            <div><dt>Organization ID</dt><dd>{stored.organizationId ?? "—"}</dd></div>
          </dl>
          {error && <p className="text-red-600 text-sm">{error}</p>}
          {success && <p className="text-green-600 text-sm">{success}</p>}
          <div className="mt-2">
            <Button type="submit" disabled={loading}>
              {loading ? "Saving..." : "Save Changes"}
            </Button>
          </div>
        </form>
      ) : (
        <dl className="detailList mt-4">
          <div><dt>Name</dt><dd>{organization?.name ?? "—"}</dd></div>
          <div><dt>Slug</dt><dd>{organization?.slug ?? "—"}</dd></div>
          <div><dt>Currency</dt><dd>{organization?.currency ?? "—"}</dd></div>
          <div><dt>Timezone</dt><dd>{organization?.timezone ?? "—"}</dd></div>
          <div><dt>Organization ID</dt><dd>{stored.organizationId ?? "—"}</dd></div>
        </dl>
      )}
    </div>
  );
}

export default function SettingsPage() {
  const { user } = useAuth();
  
  return (
    <AppShell>
      <main className="main">
        <PageHeader 
          eyebrow="Administration" 
          title="Agency settings" 
          description="Current organization and environment information. Unimplemented billing or invitation features are not shown as active controls." 
        />
        <section className="settingsGrid">
          <OrganizationSettings />
          <div className="panel">
            <div className="panelHeader">
              <div>
                <h2>Signed-in user</h2>
                <p>Current access identity</p>
              </div>
            </div>
            <dl className="detailList">
              <div><dt>Name</dt><dd>{user?.name ?? "—"}</dd></div>
              <div><dt>Email</dt><dd>{user?.email ?? "—"}</dd></div>
              <div><dt>Authentication</dt><dd>Laravel Sanctum bearer token</dd></div>
            </dl>
          </div>
          <div className="panel">
            <div className="panelHeader">
              <div>
                <h2>Production checklist</h2>
                <p>Required before onboarding real seller clients</p>
              </div>
            </div>
            <ul className="checklist">
              <li>Set production APP_KEY and database credentials.</li>
              <li>Configure Amazon LWA client ID, client secret, application ID, and exact redirect URI.</li>
              <li>Set Amazon draft mode to false after your public SP-API application is published.</li>
              <li>Run dedicated Laravel queue workers and the scheduler.</li>
              <li>Configure backups, HTTPS, monitoring, mail, and secret management.</li>
            </ul>
          </div>
          <div className="panel">
            <div className="panelHeader">
              <div>
                <h2>Feature status</h2>
                <p>Honest integration boundaries</p>
              </div>
            </div>
            <ul className="checklist">
              <li>SP-API seller authorization and listing read/write: implemented.</li>
              <li>Listing validation preview before publish: implemented.</li>
              <li>Keyword rank collection: provider interface exists; external provider not bundled.</li>
              <li>Competitor snapshots: storage exists; collector not bundled.</li>
              <li>Amazon Ads OAuth/report ingestion: not yet implemented.</li>
              <li>PDF rendering and billing: not yet implemented.</li>
            </ul>
          </div>
        </section>
      </main>
    </AppShell>
  );
}
