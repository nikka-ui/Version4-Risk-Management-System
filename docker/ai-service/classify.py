"""Express generateAiAnalysisFromReport taxonomy behind the Laravel JSON contract."""

from __future__ import annotations

import math
import re
from datetime import datetime, timezone
from typing import Any

CATEGORY_LABELS = {
    "operational": "Operational",
    "financial": "Financial",
    "compliance": "Compliance",
    "strategic": "Strategic",
    "reputational": "Reputational",
    "environmental": "Environmental Risk",
}

PRIORITY_LABELS = {
    "urgent": "Urgent",
    "high": "High",
    "medium": "Medium",
    "low": "Low",
}

DEPARTMENTS = [
    "Admin",
    "Administration",
    "Corp Plan",
    "Corp Sec",
    "Finance/Accounting",
    "HRMS",
    "Internal Audit",
    "IT",
    "MMCD",
    "Operations",
    "RMO",
    "Treasury",
]

LARAVEL_DEPT_MAP = {
    "IT": "Information Technology",
    "Finance/Accounting": "Finance",
    "HRMS": "Human Resources",
    "Admin": "Administration",
    "Corp Plan": "Business Development",
    "Corp Sec": "Administration",
    "MMCD": "Operations",
    "Treasury": "Finance",
}

IT_INFRASTRUCTURE_SIGNALS = [
    "server room", "server rack", "data center", "datacenter", "network room", "idc",
    "snmp", "syslog", "nagios", "zabbix", "prtg", "solarwinds", "monitoring alert",
    "sensor alert", "temperature alert", "thermal alert", "overheat", "overheating",
    "hardware enclosure", "hardware failure", "cooling unit", "cooling failure", "crac",
    "ups failure", "pdu", "power supply", "server outage", "server failure", "server down",
    "network outage", "network failure", "switch failure", "router failure", "firewall",
    "cyber attack", "cybersecurity", "ransomware", "malware", "phishing", "data breach",
    "database", "backup failure", "restore failure", "vpn", "domain controller",
    "active directory", "ldap", "email server", "mail server", "application crash",
    "software bug", "firmware", "patch failure", "endpoint", "workstation", "laptop",
    "storage array", "raid", "disk failure", "san", "nas", "hypervisor", "vm host",
    "virtual machine", "kubernetes", "docker", "cloud outage", "api failure",
    "unauthorized access", "password compromise", "it infrastructure", "it equipment",
    "information technology", "helpdesk", "service desk", "cpu", "memory leak",
    "rack mount", "blade server", "fiber link", "lan", "wan", "wifi", "wireless",
]

DEPARTMENT_KEYWORDS: dict[str, list[str]] = {
    "IT": [
        "server room", "server rack", "data center", "datacenter", "network room",
        "snmp", "snmp alert", "syslog", "nagios", "zabbix", "prtg", "solarwinds",
        "monitoring alert", "sensor alert", "automated sensor", "temperature alert",
        "thermal alert", "overheat", "overheating", "dangerously hot",
        "hardware enclosure", "hardware failure", "cooling unit", "cooling failure",
        "crac", "precision cooling", "ups failure", "pdu", "power supply",
        "server outage", "server failure", "server down", "network outage",
        "network failure", "switch failure", "router failure", "firewall",
        "cyber attack", "cybersecurity", "software bug", "software failure",
        "database corruption", "database outage", "hack", "hacked", "malware",
        "phishing", "ransomware", "data breach", "vpn down", "email outage",
        "email server", "mail server", "application crash", "unauthorized access",
        "password compromise", "backup failure", "it infrastructure", "domain controller",
        "active directory", "storage array", "raid", "disk failure", "hypervisor",
        "virtual machine", "kubernetes", "firmware", "patch failure", "endpoint",
        "fiber link", "lan outage", "wan outage", "wifi outage",
    ],
    "Finance/Accounting": [
        "financial fraud", "financial loss", "finance", "financial", "invoice", "payment error",
        "budget overrun", "accounting error", "tax issue", "revenue loss", "fraud", "ledger",
        "accounts payable", "accounts receivable", "billing error", "misappropriation",
        "expense report", "financial statement", "unauthorized transaction", "payroll error",
    ],
    "HRMS": [
        "hr policy", "human resources", "hiring process", "payroll discrepancy", "termination",
        "disciplinary action", "workplace harassment", "labor dispute", "employee benefits",
        "onboarding issue", "offboarding", "performance review", "collective bargaining",
        "overtime policy", "workplace violence",
    ],
    "Internal Audit": [
        "audit finding", "control deficiency", "internal control", "non-compliance",
        "regulatory breach", "policy violation", "compliance gap", "sox", "governance failure",
    ],
    "MMCD": [
        "equipment failure", "machinery breakdown", "generator failure", "elevator malfunction",
        "structural damage", "roof leak", "power outage", "electrical failure",
    ],
    "Administration": [
        "building maintenance", "facility maintenance", "facilities issue", "office maintenance",
        "housekeeping", "janitorial", "cleaning service", "security guard", "reception issue",
        "pantry", "office supplies", "furniture damage", "parking issue", "hvac", "plumbing",
        "air conditioning", "broken elevator", "water leak", "building repair",
    ],
    "Operations": [
        "operational failure", "production line", "manufacturing defect", "process failure",
        "supply chain", "logistics delay", "warehouse issue", "delivery failure",
        "inventory loss", "plant shutdown", "quality defect", "production outage",
    ],
    "Treasury": [
        "treasury", "cash management", "liquidity risk", "investment loss",
        "fund transfer error", "bank reconciliation",
    ],
    "Admin": ["records management", "general services", "document management"],
    "Corp Plan": ["corporate planning", "strategic plan", "planning office", "business plan"],
    "Corp Sec": ["corporate secretary", "board meeting", "by-laws", "governance issue"],
    "RMO": ["risk management", "enterprise risk", "risk register", "risk assessment"],
}

TITLE_DEPARTMENT_HINTS = [
    (re.compile(r"\b(financial|finance|accounting|invoice|budget|fraud|payment|revenue|tax)\b", re.I), "Finance/Accounting"),
    (re.compile(
        r"\b(server\s*room|snmp|sensor|hardware|data\s*center|datacenter|cooling\s*unit|"
        r"overheat|network|cyber|software|database|email\s+outage|it\s+outage|"
        r"server\s+rack|ups|firewall|malware|ransomware)\b",
        re.I,
    ), "IT"),
    (re.compile(r"\b(maintenance|building|facility|facilities|hvac|plumbing|janitorial|housekeeping)\b", re.I), "Administration"),
    (re.compile(r"\b(payroll|harassment|hiring|termination|hr\s+policy|workplace)\b", re.I), "HRMS"),
    (re.compile(r"\b(compliance|audit\s+finding|regulatory|policy\s+violation)\b", re.I), "Internal Audit"),
    (re.compile(r"\b(operational|production|logistics|supply\s+chain|warehouse)\b", re.I), "Operations"),
]

ROUTING_FIELD_WEIGHTS = {
    "title": 4,
    "what": 5,
    "why": 3,
    "how": 2,
    "where": 2,
}

REPORTER_DEPT_BLOCKLIST = [
    "information technology",
    "it department",
    "operations",
    "finance",
    "administration",
    "human resources",
    "hrms",
    "internal audit",
    "treasury",
    "corp plan",
    "corp sec",
    "risk management office",
    "rmo",
    "pceo",
]

IMPACT_KEYWORDS = [
    "breach", "fraud", "shutdown", "injury", "penalt", "sanction", "lawsuit",
    "leak", "outage", "major", "spill", "contamination",
]
LIKELIHOOD_KEYWORDS = [
    "often", "frequent", "recurr", "pattern", "may", "could",
    "lack of", "weak", "previous", "history",
]


def _norm(value: Any) -> str:
    return str(value or "").strip()


def js_round(n: float) -> int:
    if n >= 0:
        return int(math.floor(n + 0.5))
    return int(math.ceil(n - 0.5))


def clamp_int(n: Any, lo: int, hi: int) -> int:
    try:
        num = float(n)
    except (TypeError, ValueError):
        return lo
    if not math.isfinite(num):
        return lo
    return min(hi, max(lo, js_round(num)))


def category_label(category_id: str) -> str:
    return CATEGORY_LABELS.get(category_id, category_id)


def priority_label(priority_id: str) -> str:
    return PRIORITY_LABELS.get(priority_id, priority_id)


def map_laravel_department(dept: str) -> str:
    return LARAVEL_DEPT_MAP.get(dept, dept)


def five_w1h(payload: dict[str, Any]) -> dict[str, str]:
    raw = payload.get("fiveW1H") if isinstance(payload.get("fiveW1H"), dict) else {}
    what = _norm(raw.get("what")) or _norm(payload.get("description"))
    return {
        "what": what,
        "why": _norm(raw.get("why")),
        "where": _norm(raw.get("where")),
        "when": _norm(raw.get("when")),
        "who": _norm(raw.get("who")),
        "how": _norm(raw.get("how")),
    }


def strip_reporter_org_labels(text: str) -> str:
    s = str(text or "")
    for label in REPORTER_DEPT_BLOCKLIST:
        s = re.sub(re.escape(label), " ", s, flags=re.I)
    return re.sub(r"\s+", " ", s).strip()


def build_routing_corpus(title: str, w: dict[str, str]) -> dict[str, str]:
    return {
        "title": strip_reporter_org_labels(title),
        "what": strip_reporter_org_labels(w.get("what", "")),
        "why": strip_reporter_org_labels(w.get("why", "")),
        "how": strip_reporter_org_labels(w.get("how", "")),
        "where": strip_reporter_org_labels(w.get("where", "")),
    }


def build_incident_text(title: str, w: dict[str, str]) -> str:
    corpus = build_routing_corpus(title, w)
    return " ".join(p for p in (corpus["title"], corpus["what"], corpus["why"], corpus["where"], corpus["how"]) if p).lower()


def has_it_infrastructure_signals(text: str) -> bool:
    s = str(text or "").lower()
    return any(term in s for term in IT_INFRASTRUCTURE_SIGNALS)


def detect_category(text: str) -> str:
    s = str(text or "").lower()
    if has_it_infrastructure_signals(s):
        return "operational"

    def any_of(terms: list[str]) -> bool:
        return any(term in s for term in terms)

    if any_of([
        "environment", "environmental", "pollution", "spill", "emission",
        "waste", "hazardous", "contamination", "ecosystem", "climate",
    ]):
        return "environmental"
    if any_of([
        "audit finding", "compliance breach", "compliance violation",
        "noncompliance", "non-compliance", "regulatory breach",
        "regulatory violation", "penalt", "sanction", "iso 31000", "policy violation",
    ]):
        return "compliance"
    if any_of([
        "finance", "financial", "invoice", "payment", "budget", "tax",
        "revenue", "fraud", "accounting error", "ledger", "accounts payable",
    ]):
        return "financial"
    if any_of([
        "reputation", "reputational", "brand damage", "public relations",
        "media coverage", "negative publicity", "customer trust", "lawsuit",
        "scandal", "social media backlash",
    ]):
        return "reputational"
    if any_of(["strategy", "strategic", "market share", "competitor", "competitors", "growth", "roadmap"]):
        return "strategic"
    return "operational"


def score_keyword_hits(text: str, keywords: list[str], weight: int) -> int:
    s = str(text or "").lower()
    if not s:
        return 0
    return sum(weight for term in keywords if term in s)


def title_hint(title: str) -> str | None:
    for pattern, dept in TITLE_DEPARTMENT_HINTS:
        if pattern.search(title or ""):
            return dept
    return None


def detect_department(title: str, w: dict[str, str], category: str) -> str:
    corpus = build_routing_corpus(title, w)
    best_dept = None
    best_score = 0
    for dept, keywords in DEPARTMENT_KEYWORDS.items():
        score = 0
        for field, weight in ROUTING_FIELD_WEIGHTS.items():
            score += score_keyword_hits(corpus.get(field, ""), keywords, weight)
        if score > best_score:
            best_score = score
            best_dept = dept

    hint = title_hint(corpus["title"])
    if hint and hint in DEPARTMENTS and best_score < 4:
        best_dept = hint
        best_score = max(best_score, 4)

    if best_dept == "Admin":
        what_where = f"{corpus['what']} {corpus['where']}".lower()
        if any(cue in what_where for cue in (
            "maintenance", "building", "facility", "facilities",
            "housekeeping", "janitorial", "hvac", "plumbing",
        )):
            best_dept = "Administration"

    if best_dept and best_score > 0 and best_dept in DEPARTMENTS:
        return best_dept
    if hint and hint in DEPARTMENTS:
        return hint

    incident_blob = " ".join(corpus[k] for k in ("title", "what", "why", "how", "where"))
    if has_it_infrastructure_signals(incident_blob):
        return "IT"

    return {
        "environmental": "Administration",
        "financial": "Finance/Accounting",
        "compliance": "Internal Audit",
        "reputational": "Corp Sec",
        "strategic": "Corp Plan",
        "operational": "Operations",
        "technological": "IT",
    }.get(category, "Operations")


def risk_level_from_severity(severity: int) -> dict[str, str]:
    sev = clamp_int(severity, 1, 5)
    if sev <= 2:
        return {"id": "low", "label": "Low"}
    if sev == 3:
        return {"id": "moderate", "label": "Moderate"}
    if sev == 4:
        return {"id": "high", "label": "High"}
    return {"id": "critical", "label": "Extreme/Critical"}


def determine_priority(risk_level: dict[str, str], severity: int) -> str:
    level = risk_level.get("id") or "low"
    sev = clamp_int(severity, 1, 5)
    if level == "critical" or sev >= 5:
        return "urgent"
    if level == "high" or sev >= 4:
        return "high"
    if level == "moderate" or sev >= 3:
        return "medium"
    return "low"


def suggested_mitigation(category: str, risk_level: dict[str, str], w: dict[str, str]) -> str:
    level_label = risk_level.get("label") or "Moderate"
    what = w.get("what") or "the reported incident"
    templates = {
        "environmental": (
            f"Contain and assess environmental impact from {what}. "
            "Notify relevant authorities if required, document the incident site, "
            "and implement immediate containment measures."
        ),
        "financial": (
            f"Secure affected financial records and transactions related to {what}. "
            "Initiate reconciliation review and escalate to Finance leadership for control assessment."
        ),
        "compliance": (
            f"Document the compliance gap identified in {what}. "
            "Review applicable policies/regulations and prepare a corrective action plan with accountable owners."
        ),
        "reputational": (
            f"Prepare a stakeholder communication plan regarding {what}. "
            "Coordinate with Corporate Secretary and limit further reputational exposure."
        ),
        "strategic": (
            f"Assess strategic implications of {what} on organizational objectives. "
            "Convene planning stakeholders to evaluate impact and response options."
        ),
        "operational": (
            f"Stabilize operations affected by {what}. "
            "Implement interim controls, assign an incident owner, "
            "and monitor until permanent corrective actions are in place."
        ),
        "technological": (
            f"Contain and assess IT impact from {what}. "
            "Coordinate with IT to isolate affected systems, restore service, and document the incident timeline."
        ),
    }
    base = templates.get(category) or templates["operational"]
    return (
        f"{base} Given the {level_label} risk level, prioritize actions within 48–72 hours "
        "and report progress to the Risk Management Unit."
    )


def classify_report(payload: dict[str, Any]) -> dict[str, Any]:
    from nlp import apply_nlp_hybrid
    from transformer import apply_transformer_hybrid

    title = _norm(payload.get("title"))
    location = _norm(payload.get("location"))
    w = five_w1h(payload)
    incident_text = build_incident_text(title, w)
    supplemental = " ".join(p for p in (title, location, w.get("when")) if p).lower()

    impact_hits = sum(1 for term in IMPACT_KEYWORDS if term in incident_text)
    likelihood_hits = sum(1 for term in LIKELIHOOD_KEYWORDS if term in incident_text)
    len_boost = len(incident_text) // 450
    base = 2
    likelihood = clamp_int(base + len_boost + likelihood_hits * 1.2, 1, 5)
    impact = clamp_int(base + len_boost + impact_hits * 1.3, 1, 5)
    severity = clamp_int(js_round((likelihood + impact) / 2), 1, 5)
    risk_level = risk_level_from_severity(severity)

    category = detect_category(incident_text)
    express_dept = detect_department(title, w, category)
    department = map_laravel_department(express_dept)
    priority = determine_priority(risk_level, severity)
    mitigation = suggested_mitigation(category, risk_level, w)

    evidence_count = int(payload.get("evidenceCount") or 0)
    confidence = max(
        0.5,
        min(
            0.98,
            0.72
            + (0.1 if evidence_count >= 1 else 0)
            + (0.08 if (len(incident_text) + len(supplemental)) > 180 else 0)
            + (0.06 if len(w.get("what") or "") > 40 else 0)
            + (0.04 if department else 0),
        ),
    )
    what = w.get("what") or "the reported incident"
    why = w.get("why")
    cause = f"cause: {why}" if why else "see report for details"
    summary = (
        f'AI analysis: "{title or "Untitled"}" — {what} ({cause}). '
        f"Classified as {category_label(category)} with {risk_level['label']} severity "
        f"(likelihood {likelihood}/5, impact {impact}/5). "
        "Responsible department assigned from risk title and incident details — "
        f"not from your reporting unit: {department} with {priority_label(priority)} priority."
    )

    base_result = {
        "summary": summary,
        "likelihood": likelihood,
        "impact": impact,
        "riskCategory": category if category in CATEGORY_LABELS else "operational",
        "severity": severity,
        "riskLevel": risk_level,
        "responsibleDepartment": department,
        "priority": priority,
        "priorityLabel": priority_label(priority),
        "suggestedMitigation": mitigation,
        "confidence": round(confidence, 2),
        "manualReviewRequired": confidence < 0.75,
        "routingBasis": "title_and_incident_details",
        "routingFieldsUsed": ["title", "what", "why", "where", "how"],
        "processedAt": datetime.now(timezone.utc).isoformat().replace("+00:00", "Z"),
        "source": "ai-service",
        "engine": "taxonomy-v1",
        "mode": "taxonomy",
    }

    refined = apply_nlp_hybrid(base_result, incident_text, map_laravel_department)
    refined = apply_transformer_hybrid(refined, incident_text, map_laravel_department)
    refined["summary"] = (
        f'AI analysis: "{title or "Untitled"}" — {what} ({cause}). '
        f"Classified as {category_label(refined['riskCategory'])} with {risk_level['label']} severity "
        f"(likelihood {likelihood}/5, impact {impact}/5). "
        "Responsible department assigned from risk title and incident details — "
        f"not from your reporting unit: {refined['responsibleDepartment']} with {priority_label(priority)} priority."
    )
    return refined


def summarize_report(payload: dict[str, Any]) -> dict[str, Any]:
    result = classify_report(payload)
    return {
        "summary": result["summary"],
        "confidence": result["confidence"],
        "source": "ai-service",
        "processedAt": result["processedAt"],
        "engine": result["engine"],
        "mode": result["mode"],
    }
