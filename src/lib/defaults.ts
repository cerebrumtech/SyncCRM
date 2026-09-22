import type { Prisma } from "@/generated/prisma/client";

export const DEFAULT_PIPELINES: Array<{
  name: string;
  isDefault?: boolean;
  stages: Array<{ name: string; probability: number; isWon?: boolean; isLost?: boolean; color?: string }>;
}> = [
  {
    name: "Sales",
    isDefault: true,
    stages: [
      { name: "New Lead", probability: 10, color: "#04A2FB" },
      { name: "Contacted", probability: 20, color: "#04A2FB" },
      { name: "Qualified", probability: 40, color: "#0068FF" },
      { name: "Proposal Sent", probability: 60, color: "#0068FF" },
      { name: "Negotiation", probability: 80, color: "#1B243E" },
      { name: "Won", probability: 100, isWon: true, color: "#10B981" },
      { name: "Lost", probability: 0, isLost: true, color: "#EF4444" },
    ],
  },
  {
    name: "Onboarding",
    stages: [
      { name: "Kickoff", probability: 20, color: "#04A2FB" },
      { name: "Data Migration", probability: 50, color: "#0068FF" },
      { name: "Training", probability: 80, color: "#1B243E" },
      { name: "Live", probability: 100, isWon: true, color: "#10B981" },
      { name: "Dropped", probability: 0, isLost: true, color: "#EF4444" },
    ],
  },
];

export function defaultPipelinesCreateInput(orgId: string): Prisma.PipelineCreateManyInput[] {
  return DEFAULT_PIPELINES.map((p, i) => ({
    orgId,
    name: p.name,
    position: i,
    isDefault: !!p.isDefault,
  }));
}

export const LOST_REASONS = [
  "Price too high",
  "Chose competitor",
  "No budget",
  "No response",
  "Timing not right",
  "Not a fit",
  "Other",
];
