"use client";

export function Modal({ title, open, onClose, children }: { title: string; open: boolean; onClose(): void; children: React.ReactNode }) {
  if (!open) return null;
  return <div className="modalBackdrop" role="presentation" onMouseDown={onClose}>
    <section className="modal" role="dialog" aria-modal="true" aria-label={title} onMouseDown={(event) => event.stopPropagation()}>
      <header><h2>{title}</h2><button className="iconButton" onClick={onClose} aria-label="Close">×</button></header>
      {children}
    </section>
  </div>;
}
