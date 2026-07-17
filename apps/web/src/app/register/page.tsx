"use client";

import { ApiError } from "@/lib/api";
import { useAuth } from "@/components/AuthProvider";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";

export default function RegisterPage() {
  const { register } = useAuth();
  const router = useRouter();
  const [form, setForm] = useState({ name: "", organization_name: "", email: "", password: "", password_confirmation: "" });
  const [error, setError] = useState("");
  const [submitting, setSubmitting] = useState(false);

  async function submit(event: FormEvent) {
    event.preventDefault(); setError(""); setSubmitting(true);
    try { await register(form); router.replace("/"); }
    catch (exception) { setError(exception instanceof ApiError ? exception.message : "Unable to create account."); }
    finally { setSubmitting(false); }
  }

  return (
    <main className="min-h-screen flex items-center justify-center p-6 bg-slate-50">
      <Card className="w-full max-w-2xl shadow-lg border-slate-200">
        <CardHeader>
          <div className="flex items-center gap-3 mb-6">
            <span className="grid place-items-center w-10 h-10 rounded-xl bg-primary text-primary-foreground font-black text-xl">O</span>
            <div className="flex flex-col">
              <strong className="text-lg font-black leading-none">OopSeller</strong>
              <small className="text-muted-foreground mt-1 font-medium">Amazon Agency OS</small>
            </div>
          </div>
          <CardDescription className="uppercase tracking-widest text-xs font-bold text-muted-foreground">New workspace</CardDescription>
          <CardTitle className="text-3xl tracking-tight">Create your agency account</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={submit} className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {error && (
              <div className="col-span-full rounded-md bg-destructive/15 text-destructive font-medium p-4 text-sm">
                {error}
              </div>
            )}
            <div className="space-y-2">
              <Label htmlFor="name">Your name</Label>
              <Input id="name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
            </div>
            <div className="space-y-2">
              <Label htmlFor="organization_name">Agency name</Label>
              <Input id="organization_name" value={form.organization_name} onChange={(e) => setForm({ ...form, organization_name: e.target.value })} required />
            </div>
            <div className="space-y-2 col-span-full">
              <Label htmlFor="email">Email</Label>
              <Input id="email" type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} required />
            </div>
            <div className="space-y-2">
              <Label htmlFor="password">Password</Label>
              <Input id="password" type="password" minLength={8} value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} required />
            </div>
            <div className="space-y-2">
              <Label htmlFor="password_confirmation">Confirm password</Label>
              <Input id="password_confirmation" type="password" minLength={8} value={form.password_confirmation} onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })} required />
            </div>
            <Button size="lg" className="col-span-full mt-4 font-bold" disabled={submitting}>
              {submitting ? "Creating…" : "Create workspace"}
            </Button>
          </form>
        </CardContent>
        <CardFooter className="flex justify-center border-t p-6 mt-4 bg-slate-50/50 rounded-b-xl">
          <p className="text-sm text-muted-foreground">
            Already registered?{" "}
            <Link href="/login" className="font-semibold text-primary hover:underline">
              Sign in
            </Link>
          </p>
        </CardFooter>
      </Card>
    </main>
  );
}
