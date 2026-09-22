// Demo data for a fresh workspace: run `pnpm db:seed` after `pnpm db:migrate`.
// Safe to re-run: it skips when the demo organisation already exists.
import "dotenv/config";
import bcrypt from "bcryptjs";
import { PrismaClient } from "../src/generated/prisma/client";
import { PrismaPg } from "@prisma/adapter-pg";
import { DEFAULT_PIPELINES } from "../src/lib/defaults";
import { normalizePhone } from "../src/lib/format";

const prisma = new PrismaClient({ adapter: new PrismaPg({ connectionString: process.env.DATABASE_URL }) });

const ORG_NAME = "SyncWorks Technologies Pvt. Ltd.";
const PASSWORD = "password123";

async function main() {
  const existing = await prisma.organization.findFirst({ where: { name: ORG_NAME } });
  if (existing) {
    console.log("Demo organisation already exists — nothing to do.");
    return;
  }
  const passwordHash = await bcrypt.hash(PASSWORD, 10);
  const org = await prisma.organization.create({ data: { name: ORG_NAME } });

  const [owner, admin, rep1, rep2] = await Promise.all([
    prisma.user.create({ data: { orgId: org.id, email: "owner@syncworkstech.com", name: "Hanuman Kale", passwordHash, role: "OWNER", color: "#1B243E" } }),
    prisma.user.create({ data: { orgId: org.id, email: "admin@syncworkstech.com", name: "Sneha Patil", passwordHash, role: "ADMIN", color: "#0068FF" } }),
    prisma.user.create({ data: { orgId: org.id, email: "rahul@syncworkstech.com", name: "Rahul Deshmukh", passwordHash, role: "MEMBER", color: "#10B981" } }),
    prisma.user.create({ data: { orgId: org.id, email: "priya@syncworkstech.com", name: "Priya Joshi", passwordHash, role: "MEMBER", color: "#F59E0B" } }),
  ]);
  const team = await prisma.team.create({ data: { orgId: org.id, name: "Pune Sales", members: { create: [{ userId: rep1.id }, { userId: rep2.id }] } } });

  const pipelines = [];
  for (const [i, p] of DEFAULT_PIPELINES.entries()) {
    pipelines.push(
      await prisma.pipeline.create({
        data: {
          orgId: org.id,
          name: p.name,
          position: i,
          isDefault: !!p.isDefault,
          stages: { create: p.stages.map((s, j) => ({ name: s.name, position: j, probability: s.probability, isWon: !!s.isWon, isLost: !!s.isLost, color: s.color ?? "#0068FF" })) },
        },
        include: { stages: { orderBy: { position: "asc" } } },
      }),
    );
  }
  const sales = pipelines[0]!;
  const stage = (name: string) => sales.stages.find((s) => s.name === name)!;

  await prisma.customFieldDefinition.createMany({
    data: [
      { orgId: org.id, entity: "COMPANY", key: "gst_number", label: "GST number", type: "TEXT", position: 0 },
      { orgId: org.id, entity: "COMPANY", key: "branches", label: "Branches", type: "NUMBER", position: 1 },
      { orgId: org.id, entity: "DEAL", key: "licence_type", label: "Licence type", type: "SELECT", options: ["Standard", "Professional", "Enterprise"], position: 0 },
      { orgId: org.id, entity: "CONTACT", key: "preferred_language", label: "Preferred language", type: "SELECT", options: ["Marathi", "Hindi", "English"], position: 0 },
    ],
  });

  const products = await Promise.all([
    prisma.product.create({ data: { orgId: org.id, name: "SyncLMS Standard (annual)", sku: "LMS-STD-1Y", price: 120000, taxRate: 18, description: "Loan management for up to 5 branches." } }),
    prisma.product.create({ data: { orgId: org.id, name: "SyncLMS Professional (annual)", sku: "LMS-PRO-1Y", price: 240000, taxRate: 18, description: "Unlimited branches, eNACH, bureau integration." } }),
    prisma.product.create({ data: { orgId: org.id, name: "SyncVerify KYC pack (1,000 checks)", sku: "VER-1K", price: 15000, taxRate: 18 } }),
    prisma.product.create({ data: { orgId: org.id, name: "Implementation & training", sku: "SVC-IMPL", price: 50000, taxRate: 18 } }),
    prisma.product.create({ data: { orgId: org.id, name: "Legacy module (discontinued)", sku: "LMS-LEG", price: 10000, taxRate: 18, isActive: false } }),
  ]);

  const companiesData = [
    { name: "Shivneri Nagari Sahakari Patsanstha", industry: "Co-operative Credit Society", city: "Pune", state: "Maharashtra", owner: rep1, gst: "27AAAAA0000A1Z5", branches: 6, tags: ["priority", "patsanstha"] },
    { name: "Godavari Urban Credit Society", industry: "Co-operative Credit Society", city: "Nashik", state: "Maharashtra", owner: rep2, gst: "27BBBBB1111B1Z2", branches: 3, tags: ["patsanstha"] },
    { name: "Vidarbha Microfinance Ltd", industry: "Microfinance", city: "Nagpur", state: "Maharashtra", owner: rep1, gst: "27CCCCC2222C1Z9", branches: 14, tags: ["mfi"] },
    { name: "Konkan Finserv NBFC", industry: "NBFC", city: "Ratnagiri", state: "Maharashtra", owner: rep2, gst: "27DDDDD3333D1Z4", branches: 2, tags: ["nbfc", "priority"] },
    { name: "Marathwada Gramin Patsanstha", industry: "Co-operative Credit Society", city: "Aurangabad", state: "Maharashtra", owner: admin, gst: null, branches: 4, tags: ["patsanstha"] },
  ];
  const companies = [];
  for (const c of companiesData) {
    companies.push(
      await prisma.company.create({
        data: { orgId: org.id, name: c.name, industry: c.industry, city: c.city, state: c.state, country: "India", ownerId: c.owner.id, tags: c.tags, customFields: { gst_number: c.gst, branches: c.branches } },
      }),
    );
  }

  const contactsData = [
    { firstName: "Priya", lastName: "Shah", email: "priya.shah@shivneri.coop", phone: "+91 98220 11111", jobTitle: "Chairman", company: 0, owner: rep1, lang: "Marathi" },
    { firstName: "Amol", lastName: "Kulkarni", email: "amol@shivneri.coop", phone: "+91 98220 22222", jobTitle: "CEO", company: 0, owner: rep1, lang: "Marathi" },
    { firstName: "Sunita", lastName: "Pawar", email: "sunita@godavari.coop", phone: "+91 98230 33333", jobTitle: "Manager", company: 1, owner: rep2, lang: "Marathi" },
    { firstName: "Rakesh", lastName: "Meshram", email: "rakesh@vidarbhamf.in", phone: "+91 98240 44444", jobTitle: "Head of Operations", company: 2, owner: rep1, lang: "Hindi" },
    { firstName: "Neha", lastName: "Sawant", email: "neha@konkanfinserv.in", phone: "+91 98250 55555", jobTitle: "Director", company: 3, owner: rep2, lang: "English" },
    { firstName: "Vikram", lastName: "Jadhav", email: "vikram@konkanfinserv.in", phone: "+91 98250 66666", jobTitle: "IT Manager", company: 3, owner: rep2, lang: "English" },
    { firstName: "Manisha", lastName: "Gaikwad", email: "manisha@marathwada.coop", phone: "+91 98260 77777", jobTitle: "Secretary", company: 4, owner: admin, lang: "Marathi" },
    { firstName: "Sachin", lastName: "More", email: null, phone: "+91 98270 88888", jobTitle: "Consultant", company: null, owner: rep1, lang: "Marathi" },
  ];
  const contacts = [];
  for (const c of contactsData) {
    contacts.push(
      await prisma.contact.create({
        data: {
          orgId: org.id,
          firstName: c.firstName,
          lastName: c.lastName,
          email: c.email,
          phone: c.phone,
          phoneNormalized: normalizePhone(c.phone),
          jobTitle: c.jobTitle,
          companyId: c.company === null ? null : companies[c.company]!.id,
          ownerId: c.owner.id,
          tags: c.company === 0 || c.company === 3 ? ["decision-maker"] : [],
          customFields: { preferred_language: c.lang },
        },
      }),
    );
  }

  const day = 86_400_000;
  const now = Date.now();
  const dealsData = [
    { title: "SyncLMS Professional — Shivneri", stage: "Negotiation", amount: 283200, contact: 0, company: 0, owner: rep1, close: 20, items: [products[1]!, products[3]!] },
    { title: "SyncLMS Standard — Godavari", stage: "Proposal Sent", amount: 141600, contact: 2, company: 1, owner: rep2, close: 35, items: [products[0]!] },
    { title: "SyncVerify KYC — Vidarbha MF", stage: "Qualified", amount: 53100, contact: 3, company: 2, owner: rep1, close: 45, items: [products[2]!, products[2]!, products[2]!] },
    { title: "SyncNBFC Suite — Konkan Finserv", stage: "Contacted", amount: 500000, contact: 4, company: 3, owner: rep2, close: 60, items: [] },
    { title: "SyncLMS Standard — Marathwada", stage: "New Lead", amount: 0, contact: 6, company: 4, owner: admin, close: 90, items: [] },
    { title: "SyncLMS Standard — Konkan (branch 2)", stage: "Won", amount: 141600, contact: 5, company: 3, owner: rep2, close: -10, closed: -10, items: [products[0]!] },
    { title: "SyncVerify pilot — Godavari", stage: "Won", amount: 17700, contact: 2, company: 1, owner: rep2, close: -40, closed: -40, items: [products[2]!] },
    { title: "SyncLMS Pro — Vidarbha (2025 renewal)", stage: "Lost", amount: 283200, contact: 3, company: 2, owner: rep1, close: -25, closed: -25, lost: "Price too high", items: [] },
  ];
  const deals = [];
  for (const [i, d] of dealsData.entries()) {
    const st = stage(d.stage);
    const deal = await prisma.deal.create({
      data: {
        orgId: org.id,
        title: d.title,
        pipelineId: sales.id,
        stageId: st.id,
        status: st.isWon ? "WON" : st.isLost ? "LOST" : "OPEN",
        amount: d.amount,
        amountIsManual: d.items.length === 0,
        expectedCloseDate: new Date(now + d.close * day),
        closedAt: d.closed !== undefined ? new Date(now + d.closed * day) : null,
        lostReason: d.lost ?? null,
        contactId: contacts[d.contact]!.id,
        companyId: companies[d.company]!.id,
        ownerId: d.owner.id,
        position: i,
        tags: d.amount > 200000 ? ["big-ticket"] : [],
        customFields: { licence_type: d.title.includes("Pro") ? "Professional" : "Standard" },
        lineItems: {
          create: d.items.map((p, j) => {
            const price = Number(p.price);
            const tax = Number(p.taxRate);
            return { productId: p.id, name: p.name, quantity: 1, unitPrice: price, discountPercent: 0, taxRate: tax, total: Math.round(price * (1 + tax / 100) * 100) / 100, position: j };
          }),
        },
      },
    });
    deals.push(deal);
  }

  await prisma.activity.createMany({
    data: [
      { orgId: org.id, type: "TASK", title: "Send revised proposal to Shivneri", dueAt: new Date(now + 1 * day), assigneeId: rep1.id, createdById: rep1.id, contactId: contacts[0]!.id, companyId: companies[0]!.id, dealId: deals[0]!.id, reminderMinutes: 60 },
      { orgId: org.id, type: "TASK", title: "Follow up on KYC pilot pricing", dueAt: new Date(now - 2 * day), assigneeId: rep1.id, createdById: rep1.id, contactId: contacts[3]!.id, companyId: companies[2]!.id, dealId: deals[2]!.id },
      { orgId: org.id, type: "EVENT", title: "Demo at Godavari head office", dueAt: new Date(now + 3 * day), endAt: new Date(now + 3 * day + 3600_000), location: "Nashik", attendees: ["sunita@godavari.coop"], assigneeId: rep2.id, createdById: rep2.id, contactId: contacts[2]!.id, companyId: companies[1]!.id, dealId: deals[1]!.id, reminderMinutes: 15 },
      { orgId: org.id, type: "CALL", title: "Intro call with Neha", dueAt: new Date(now - 1 * day), status: "COMPLETED", completedAt: new Date(now - 1 * day), callDirection: "OUTBOUND", callDurationSec: 900, callOutcome: "Interested", assigneeId: rep2.id, createdById: rep2.id, contactId: contacts[4]!.id, companyId: companies[3]!.id, dealId: deals[3]!.id },
      { orgId: org.id, type: "TASK", title: "Weekly pipeline review", dueAt: new Date(now + 2 * day), recurrence: "WEEKLY", assigneeId: admin.id, createdById: owner.id },
      { orgId: org.id, type: "CALL", title: "Call Manisha about board approval", dueAt: new Date(now + 1 * day), callDirection: "OUTBOUND", assigneeId: admin.id, createdById: admin.id, contactId: contacts[6]!.id, companyId: companies[4]!.id, dealId: deals[4]!.id },
    ],
  });

  await prisma.note.createMany({
    data: [
      { orgId: org.id, body: "Board meets on the 28th; Priya wants the proposal before then.", authorId: rep1.id, contactId: contacts[0]!.id },
      { orgId: org.id, body: "Currently on Excel + Tally. 6 branches, ~9,000 members.", authorId: rep1.id, companyId: companies[0]!.id },
      { orgId: org.id, body: "Competitor quoted lower; emphasise eNACH savings.", authorId: rep1.id, dealId: deals[0]!.id },
    ],
  });

  await prisma.tag.createMany({ data: ["priority", "patsanstha", "mfi", "nbfc", "decision-maker", "big-ticket"].map((name) => ({ orgId: org.id, name })), skipDuplicates: true });
  await prisma.savedView.create({ data: { orgId: org.id, entity: "COMPANY", name: "Priority accounts", filters: { tag: "priority" }, ownerId: owner.id, isShared: true } });
  await prisma.auditLog.create({ data: { orgId: org.id, actorId: owner.id, action: "seed", entity: "Organization", entityId: org.id, entityLabel: ORG_NAME } });

  console.log(`Seeded ${ORG_NAME}`);
  console.log(`Sign in with owner@syncworkstech.com / ${PASSWORD} (also admin@, rahul@, priya@ — same password). Team: ${team.name}`);
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(() => prisma.$disconnect());
