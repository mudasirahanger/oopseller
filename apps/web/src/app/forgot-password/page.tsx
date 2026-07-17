"use client";

import { ApiError, apiFetch } from "@/lib/api";
import Link from "next/link";
import { FormEvent, useState } from "react";

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState("");
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [submitting, setSubmitting] = useState(false);

  async function submit(event: FormEvent) {
    event.preventDefault(); setError(""); setMessage(""); setSubmitting(true);
    try {
      const response = await apiFetch<{ message: string }>("/auth/forgot-password", {
        method: "POST",
        body: JSON.stringify({ email }),
      }, false);
      setMessage(response.message);
    } catch (exception) {
      setError(exception instanceof ApiError ? exception.message : "Unable to send the reset link.");
    } finally {
      setSubmitting(false);
    }
  }

  return <main className="authPage"><section className="authCard"><div className="authBrand"><span className="brandMark">O</span><div><strong>OopSeller</strong><small>Amazon Agency OS</small></div></div><p className="eyebrow">Account recovery</p><h1>Forgot password</h1><p>Enter your account email and we will send a password reset link.</p><form className="formStack" onSubmit={submit}>{error && <div className="errorBox">{error}</div>}{message && <div className="successBox">{message}</div>}<label>Email<input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required autoComplete="email" /></label><button className="primary" disabled={submitting}>{submitting ? "Sending…" : "Send reset link"}</button></form><p className="authSwitch">Remembered it? <Link href="/login">Back to sign in</Link></p></section></main>;
}
