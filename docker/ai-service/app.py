"""RMS AI service — Phase 13 slice 1: CPU transformer hybrid classify."""
from __future__ import annotations

import os

from flask import Flask, jsonify, request

from classify import classify_report, summarize_report
from transformer import ENGINE, MODE

app = Flask(__name__)


@app.route("/health")
def health():
    return jsonify({
        "status": "ok",
        "service": "ai-service",
        "mode": MODE,
        "engine": ENGINE,
        "device": "cpu",
        "phase": 13,
        "slice": 1,
    })


@app.route("/classify", methods=["POST"])
def classify():
    body = request.get_json(silent=True) or {}
    if not isinstance(body, dict):
        return jsonify({"error": "JSON object required"}), 400
    return jsonify(classify_report(body))


@app.route("/summarize", methods=["POST"])
def summarize():
    body = request.get_json(silent=True) or {}
    if not isinstance(body, dict):
        return jsonify({"error": "JSON object required"}), 400
    return jsonify(summarize_report(body))


if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5000))
    app.run(host="0.0.0.0", port=port)
