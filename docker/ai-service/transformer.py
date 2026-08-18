"""CPU transformer encoder (hashed char-ngram embeddings + self-attention).

Refines taxonomy + TF-IDF classify without a GPU or extra Python packages.
A future GPU/ONNX backend can swap in behind the same /classify contract.
"""

from __future__ import annotations

import hashlib
import math
import re
from typing import Any, Callable

import numpy as np

from nlp import CATEGORY_EXEMPLARS, DEPT_EXEMPLARS, _top_key

DIM = 32
HEADS = 4
MAX_TOKENS = 48
ENGINE = "transformer-hybrid-v1"
MODE = "transformer-hybrid"

_TOKEN_RE = re.compile(r"[a-z0-9]+")

_category_vectors: dict[str, np.ndarray] | None = None
_dept_vectors: dict[str, np.ndarray] | None = None


def _ngram_vec(token: str) -> np.ndarray:
    grams = [token[i : i + 3] for i in range(max(1, len(token) - 2))] or [token]
    acc = np.zeros(DIM, dtype=np.float32)
    for gram in grams:
        digest = hashlib.sha256(gram.encode("utf-8")).digest()
        seed = int.from_bytes(digest[:8], "little", signed=False) % (2**32)
        acc += np.random.default_rng(seed).normal(0.0, 1.0, DIM).astype(np.float32)
    norm = float(np.linalg.norm(acc)) + 1e-8
    return acc / norm


def _tokenize(text: str) -> list[str]:
    tokens = _TOKEN_RE.findall((text or "").lower())
    return tokens[:MAX_TOKENS] or ["empty"]


def _positional(n: int) -> np.ndarray:
    pos = np.zeros((n, DIM), dtype=np.float32)
    for i in range(n):
        for k in range(0, DIM, 2):
            div = 10000.0 ** (k / DIM)
            pos[i, k] = math.sin(i / div)
            if k + 1 < DIM:
                pos[i, k + 1] = math.cos(i / div)
    return pos


def encode(text: str) -> np.ndarray:
    tokens = _tokenize(text)
    stacked = np.stack([_ngram_vec(t) for t in tokens], axis=0)
    stacked = stacked + _positional(len(tokens))

    head_dim = DIM // HEADS
    scale = 1.0 / math.sqrt(head_dim)
    parts: list[np.ndarray] = []
    for h in range(HEADS):
        sl = slice(h * head_dim, (h + 1) * head_dim)
        qk = stacked[:, sl]
        scores = (qk @ qk.T) * scale
        scores = scores - scores.max(axis=1, keepdims=True)
        weights = np.exp(scores)
        weights = weights / (weights.sum(axis=1, keepdims=True) + 1e-8)
        parts.append(weights @ qk)
    ctx = np.concatenate(parts, axis=1)
    pooled = ctx.mean(axis=0)
    norm = float(np.linalg.norm(pooled)) + 1e-8
    return pooled / norm


def _cosine(a: np.ndarray, b: np.ndarray) -> float:
    return float(np.dot(a, b) / ((np.linalg.norm(a) * np.linalg.norm(b)) + 1e-8))


def _ensure_exemplars() -> None:
    global _category_vectors, _dept_vectors
    if _category_vectors is None:
        _category_vectors = {k: encode(v) for k, v in CATEGORY_EXEMPLARS.items()}
    if _dept_vectors is None:
        _dept_vectors = {k: encode(v) for k, v in DEPT_EXEMPLARS.items()}


def score_categories(text: str) -> dict[str, float]:
    _ensure_exemplars()
    assert _category_vectors is not None
    vec = encode(text)
    return {k: max(0.0, _cosine(vec, v)) for k, v in _category_vectors.items()}


def score_departments(text: str) -> dict[str, float]:
    _ensure_exemplars()
    assert _dept_vectors is not None
    vec = encode(text)
    return {k: max(0.0, _cosine(vec, v)) for k, v in _dept_vectors.items()}


def _blend_category(taxonomy_category: str, text: str) -> tuple[str, dict[str, float]]:
    scores = score_categories(text)
    top, top_score = _top_key(scores)
    tax_score = scores.get(taxonomy_category, 0.0)

    if taxonomy_category == "operational" and top != "operational":
        if top_score >= 0.22 and top_score - scores.get("operational", 0.0) >= 0.08:
            return top, scores

    if top != taxonomy_category and top_score >= 0.25 and top_score >= tax_score + 0.08:
        return top, scores

    return taxonomy_category, scores


def _blend_department(
    taxonomy_dept: str,
    text: str,
    reverse_map: dict[str, str],
) -> tuple[str, dict[str, float]]:
    scores = score_departments(text)
    top, top_score = _top_key(scores)
    tax_express = reverse_map.get(taxonomy_dept, taxonomy_dept)
    tax_score = scores.get(tax_express, 0.0)

    if top_score >= 0.22 and top_score >= tax_score + 0.08:
        return top, scores

    return tax_express, scores


def apply_transformer_hybrid(
    result: dict[str, Any],
    incident_text: str,
    map_laravel_dept: Callable[[str], str],
) -> dict[str, Any]:
    """Refine nlp-hybrid output with a CPU transformer encoder."""
    category, cat_scores = _blend_category(str(result.get("riskCategory") or "operational"), incident_text)
    reverse_dept = {map_laravel_dept(k): k for k in DEPT_EXEMPLARS}
    express_dept, dept_scores = _blend_department(
        str(result.get("responsibleDepartment") or "Operations"),
        incident_text,
        reverse_dept,
    )
    department = map_laravel_dept(express_dept)

    confidence = float(result.get("confidence") or 0.72)
    tax_cat = str(result.get("riskCategory") or "")
    if category == tax_cat and cat_scores.get(category, 0) >= 0.10:
        confidence = min(0.98, confidence + 0.03)
    elif category != tax_cat:
        confidence = max(0.55, confidence - 0.02)

    top_cat, top_cat_score = _top_key(cat_scores)
    top_dept, top_dept_score = _top_key(dept_scores)
    if top_cat_score >= 0.14:
        confidence = min(0.98, confidence + 0.02)
    if top_dept_score >= 0.14 and express_dept == top_dept:
        confidence = min(0.98, confidence + 0.02)

    result = dict(result)
    result["riskCategory"] = category
    result["responsibleDepartment"] = department
    result["confidence"] = round(confidence, 2)
    result["manualReviewRequired"] = confidence < 0.75
    result["engine"] = ENGINE
    result["mode"] = MODE
    result["device"] = "cpu"
    result["transformerScores"] = {
        "categories": {k: round(v, 4) for k, v in sorted(cat_scores.items(), key=lambda x: -x[1])[:3]},
        "departments": {k: round(v, 4) for k, v in sorted(dept_scores.items(), key=lambda x: -x[1])[:3]},
    }
    return result
