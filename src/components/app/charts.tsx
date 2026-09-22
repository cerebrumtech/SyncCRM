import Link from "next/link";
import { formatCompactINR } from "@/lib/format";

export type BarRow = { key: string; label: string; value: number; display: string; href?: string; sub?: string };

// Horizontal single-series bar list: one hue for magnitude, direct labels, drill-down links.
export function BarList({ rows, color = "#0068FF", empty = "No data for this period." }: { rows: BarRow[]; color?: string; empty?: string }) {
  if (rows.length === 0) return <p className="py-6 text-center text-sm text-ink-500">{empty}</p>;
  const max = Math.max(...rows.map((r) => r.value), 1);
  return (
    <ul className="space-y-2">
      {rows.map((r) => {
        const inner = (
          <>
            <div className="flex items-baseline justify-between text-sm">
              <span className="truncate font-medium text-ink">
                {r.label}
                {r.sub && <span className="ml-1.5 text-xs font-normal text-ink-500">{r.sub}</span>}
              </span>
              <span className="ml-3 shrink-0 tabular text-ink-700">{r.display}</span>
            </div>
            <div className="mt-1 h-2 w-full rounded-full bg-surface">
              <div className="h-2 rounded-full" style={{ width: `${Math.max(2, (r.value / max) * 100)}%`, background: color }} title={`${r.label}: ${r.display}`} />
            </div>
          </>
        );
        return (
          <li key={r.key}>
            {r.href ? (
              <Link href={r.href} className="block rounded-md px-1 py-0.5 hover:bg-page" title="Open records">
                {inner}
              </Link>
            ) : (
              <div className="px-1 py-0.5">{inner}</div>
            )}
          </li>
        );
      })}
    </ul>
  );
}

export type MonthPoint = { key: string; label: string; won: number; lost: number; wonCount: number; lostCount: number };

// Grouped monthly bars for won vs lost revenue. Two semantic (status) hues, validated for CVD; values are direct-labelled.
export function WonLostChart({ points, hrefFor }: { points: MonthPoint[]; hrefFor: (p: MonthPoint, status: "WON" | "LOST") => string }) {
  const W = 640;
  const H = 220;
  const padL = 8;
  const padB = 28;
  const padT = 22;
  const max = Math.max(...points.flatMap((p) => [p.won, p.lost]), 1);
  const group = (W - padL * 2) / points.length;
  const barW = Math.min(36, group * 0.32);
  const y = (v: number) => padT + (H - padT - padB) * (1 - v / max);
  const gridVals = [0.25, 0.5, 0.75, 1].map((f) => f * max);

  return (
    <div>
      <div className="mb-2 flex items-center gap-4 text-xs text-ink-700">
        <span className="flex items-center gap-1.5">
          <span className="h-2.5 w-2.5 rounded-sm bg-success" /> Won
        </span>
        <span className="flex items-center gap-1.5">
          <span className="h-2.5 w-2.5 rounded-sm bg-danger" /> Lost
        </span>
      </div>
      <svg viewBox={`0 0 ${W} ${H}`} className="h-auto w-full" role="img" aria-label="Won and lost revenue by month">
        {gridVals.map((g) => (
          <line key={g} x1={padL} x2={W - padL} y1={y(g)} y2={y(g)} stroke="#E5E5E5" strokeWidth={1} />
        ))}
        <line x1={padL} x2={W - padL} y1={y(0)} y2={y(0)} stroke="#D1D1D1" strokeWidth={1} />
        {points.map((p, i) => {
          const cx = padL + group * i + group / 2;
          const bars: Array<{ v: number; c: number; color: string; x: number; status: "WON" | "LOST" }> = [
            { v: p.won, c: p.wonCount, color: "#10B981", x: cx - barW - 1, status: "WON" },
            { v: p.lost, c: p.lostCount, color: "#EF4444", x: cx + 1, status: "LOST" },
          ];
          return (
            <g key={p.key}>
              {bars.map((b) => (
                <Link key={b.status} href={hrefFor(p, b.status)}>
                  <rect x={b.x} y={y(b.v)} width={barW} height={Math.max(0, y(0) - y(b.v))} rx={3} fill={b.color}>
                    <title>{`${p.label} · ${b.status === "WON" ? "Won" : "Lost"}: ${formatCompactINR(b.v)} (${b.c} deal${b.c === 1 ? "" : "s"})`}</title>
                  </rect>
                  {b.v > 0 && (
                    <text x={b.x + barW / 2} y={y(b.v) - 4} textAnchor="middle" fontSize={10} fill="#4A4A4A" className="tabular">
                      {formatCompactINR(b.v)}
                    </text>
                  )}
                </Link>
              ))}
              <text x={cx} y={H - 8} textAnchor="middle" fontSize={11} fill="#767676">
                {p.label}
              </text>
            </g>
          );
        })}
      </svg>
    </div>
  );
}
