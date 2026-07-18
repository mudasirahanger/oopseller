import Link from "next/link";
import type { Route } from "next";

export function EmptyState({ title, description, actionLabel, actionHref }: { title: string; description: string; actionLabel?: string; actionHref?: Route }) {
  return <div className="emptyState"><strong>{title}</strong><p>{description}</p>{actionLabel && actionHref && <Link className="primary emptyStateAction" href={actionHref}>{actionLabel}</Link>}</div>;
}
