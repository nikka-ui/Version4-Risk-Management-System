"""TF-IDF cosine similarity blended with taxonomy-v1 classify scores."""

from __future__ import annotations

from typing import Any

from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

CATEGORY_EXEMPLARS: dict[str, str] = {
    "operational": (
        "operational failure production outage server down network outage downtime process failure "
        "service delivery supply chain logistics warehouse delivery failure quality defect plant shutdown "
        "it infrastructure server room data center switch failure cyber attack malware ransomware"
    ),
    "financial": (
        "financial fraud budget overrun invoice payment error accounting error tax issue revenue loss "
        "ledger accounts payable accounts receivable billing error misappropriation unauthorized transaction "
        "finance financial disbursement treasury cash management"
    ),
    "compliance": (
        "audit finding compliance breach regulatory violation non-compliance policy violation "
        "internal control governance failure sox compliance gap regulatory breach iso 31000"
    ),
    "strategic": (
        "strategic plan market share competitor growth roadmap corporate planning business plan "
        "strategy strategic objectives organizational objectives planning office"
    ),
    "reputational": (
        "reputation reputational brand damage public relations media coverage negative publicity "
        "customer trust lawsuit scandal social media backlash stakeholder trust"
    ),
    "environmental": (
        "environment environmental pollution spill emission waste hazardous contamination ecosystem "
        "climate occupational safety chemical spill environmental impact"
    ),
}

DEPT_EXEMPLARS: dict[str, str] = {
    "IT": (
        "server room data center network outage cyber attack malware ransomware firewall database "
        "email server vpn active directory helpdesk it infrastructure software failure"
    ),
    "Finance/Accounting": (
        "finance financial invoice budget fraud accounting ledger accounts payable tax revenue loss"
    ),
    "HRMS": (
        "human resources payroll harassment hiring termination workplace violence employee benefits "
        "disciplinary action labor dispute"
    ),
    "Internal Audit": (
        "audit finding compliance breach policy violation internal control regulatory breach sox"
    ),
    "Administration": (
        "building maintenance facility hvac plumbing janitorial housekeeping office supplies parking"
    ),
    "Operations": (
        "operational failure production line supply chain logistics warehouse delivery manufacturing"
    ),
    "Treasury": (
        "treasury cash management liquidity investment fund transfer bank reconciliation"
    ),
    "Corp Plan": ("corporate planning strategic plan business plan planning office"),
    "Corp Sec": ("corporate secretary board meeting governance by-laws"),
    "MMCD": ("equipment failure machinery generator elevator structural damage power outage electrical"),
    "RMO": ("risk management enterprise risk risk register risk assessment"),
    "Admin": ("records management general services document management"),
}

_category_vectorizer: TfidfVectorizer | None = None
_category_matrix = None
_category_ids: list[str] = []

_dept_vectorizer: TfidfVectorizer | None = None
_dept_matrix = None
_dept_ids: list[str] = []


def _build_vectorizer() -> TfidfVectorizer:
    return TfidfVectorizer(ngram_range=(1, 2), min_df=1, sublinear_tf=True)


def _ensure_category_model() -> None:
    global _category_vectorizer, _category_matrix, _category_ids
    if _category_vectorizer is not None:
        return
    _category_ids = list(CATEGORY_EXEMPLARS.keys())
    docs = [CATEGORY_EXEMPLARS[c] for c in _category_ids]
    _category_vectorizer = _build_vectorizer()
    _category_matrix = _category_vectorizer.fit_transform(docs)


def _ensure_dept_model() -> None:
    global _dept_vectorizer, _dept_matrix, _dept_ids
    if _dept_vectorizer is not None:
        return
    _dept_ids = list(DEPT_EXEMPLARS.keys())
    docs = [DEPT_EXEMPLARS[d] for d in _dept_ids]
    _dept_vectorizer = _build_vectorizer()
    _dept_matrix = _dept_vectorizer.fit_transform(docs)


def score_categories(text: str) -> dict[str, float]:
    _ensure_category_model()
    assert _category_vectorizer is not None and _category_matrix is not None
    vec = _category_vectorizer.transform([text or ""])
    sims = cosine_similarity(vec, _category_matrix)[0]
    return {cat: float(sims[i]) for i, cat in enumerate(_category_ids)}


def score_departments(text: str) -> dict[str, float]:
    _ensure_dept_model()
    assert _dept_vectorizer is not None and _dept_matrix is not None
    vec = _dept_vectorizer.transform([text or ""])
    sims = cosine_similarity(vec, _dept_matrix)[0]
    return {dept: float(sims[i]) for i, dept in enumerate(_dept_ids)}


def _top_key(scores: dict[str, float]) -> tuple[str, float]:
    if not scores:
        return "operational", 0.0
    top = max(scores, key=scores.get)  # type: ignore[arg-type]
    return top, scores[top]


def blend_category(taxonomy_category: str, text: str) -> tuple[str, dict[str, float]]:
    scores = score_categories(text)
    top, top_score = _top_key(scores)
    tax_score = scores.get(taxonomy_category, 0.0)

    if taxonomy_category == "operational" and top != "operational":
        if top_score >= 0.10 and top_score - scores.get("operational", 0.0) >= 0.035:
            return top, scores

    if top != taxonomy_category and top_score >= 0.16 and top_score >= tax_score + 0.05:
        return top, scores

    return taxonomy_category, scores


def blend_department(
    taxonomy_dept: str,
    text: str,
    reverse_map: dict[str, str],
) -> tuple[str, dict[str, float]]:
    scores = score_departments(text)
    top, top_score = _top_key(scores)
    tax_express = reverse_map.get(taxonomy_dept, taxonomy_dept)
    tax_score = scores.get(tax_express, 0.0)

    if top_score >= 0.14 and top_score >= tax_score + 0.05:
        return top, scores

    return tax_express, scores


def apply_nlp_hybrid(result: dict[str, Any], incident_text: str, map_laravel_dept) -> dict[str, Any]:
    """Refine taxonomy classify output with TF-IDF similarity scores."""
    category, cat_scores = blend_category(str(result.get("riskCategory") or "operational"), incident_text)
    reverse_dept = {map_laravel_dept(k): k for k in DEPT_EXEMPLARS}
    express_dept, dept_scores = blend_department(
        str(result.get("responsibleDepartment") or "Operations"),
        incident_text,
        reverse_dept,
    )
    department = map_laravel_dept(express_dept)

    confidence = float(result.get("confidence") or 0.72)
    tax_cat = str(result.get("riskCategory") or "")
    if category == tax_cat and cat_scores.get(category, 0) >= 0.08:
        confidence = min(0.98, confidence + 0.04)
    elif category != tax_cat:
        confidence = max(0.55, confidence - 0.03)

    top_cat, top_cat_score = _top_key(cat_scores)
    top_dept, top_dept_score = _top_key(dept_scores)
    if top_cat_score >= 0.12:
        confidence = min(0.98, confidence + 0.02)
    if top_dept_score >= 0.12 and express_dept == top_dept:
        confidence = min(0.98, confidence + 0.02)

    result = dict(result)
    result["riskCategory"] = category
    result["responsibleDepartment"] = department
    result["confidence"] = round(confidence, 2)
    result["manualReviewRequired"] = confidence < 0.75
    result["engine"] = "nlp-hybrid-v1"
    result["mode"] = "nlp-hybrid"
    result["nlpScores"] = {
        "categories": {k: round(v, 4) for k, v in sorted(cat_scores.items(), key=lambda x: -x[1])[:3]},
        "departments": {k: round(v, 4) for k, v in sorted(dept_scores.items(), key=lambda x: -x[1])[:3]},
    }
    return result
