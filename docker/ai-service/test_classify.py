"""Unit tests for taxonomy classify (Express generateAiAnalysisFromReport port)."""

import unittest

from classify import classify_report


class ClassifyTaxonomyTest(unittest.TestCase):
    def test_network_outage_routes_to_it_operational(self):
        result = classify_report({
            "title": "Network outage risk",
            "location": "Data center",
            "fiveW1H": {
                "what": "Core switch failed during peak hours",
                "why": "Aging hardware without redundancy",
                "where": "Rack A",
                "when": "Morning",
                "who": "IT ops",
                "how": "Single point of failure caused outage",
            },
            "evidenceCount": 1,
        })
        self.assertEqual(result["riskCategory"], "operational")
        self.assertEqual(result["responsibleDepartment"], "Information Technology")
        self.assertEqual(result["mode"], "transformer-hybrid")
        self.assertEqual(result["engine"], "transformer-hybrid-v1")
        self.assertEqual(result["device"], "cpu")
        self.assertTrue(result["suggestedMitigation"])
        self.assertIn(result["priority"], ("urgent", "high", "medium", "low"))
        self.assertIn("nlpScores", result)
        self.assertIn("transformerScores", result)

    def test_budget_fraud_routes_to_finance(self):
        result = classify_report({
            "title": "Budget fraud risk",
            "fiveW1H": {"what": "Unauthorized invoice payments and budget overrun"},
            "evidenceCount": 1,
        })
        self.assertEqual(result["riskCategory"], "financial")
        self.assertEqual(result["responsibleDepartment"], "Finance")

    def test_compliance_audit_finding(self):
        result = classify_report({
            "title": "Policy violation audit finding",
            "fiveW1H": {"what": "Regulatory breach and non-compliance with internal control"},
        })
        self.assertEqual(result["riskCategory"], "compliance")
        self.assertEqual(result["responsibleDepartment"], "Internal Audit")

    def test_environmental_spill(self):
        result = classify_report({
            "title": "Hazardous waste spill",
            "fiveW1H": {"what": "Chemical pollution spill near the loading bay"},
        })
        self.assertEqual(result["riskCategory"], "environmental")
        self.assertEqual(result["responsibleDepartment"], "Administration")

    def test_reputational_media_nlp_refinement(self):
        result = classify_report({
            "title": "Social media backlash",
            "fiveW1H": {"what": "Negative publicity and brand damage after viral scandal"},
            "evidenceCount": 1,
        })
        self.assertEqual(result["riskCategory"], "reputational")
        self.assertIn("nlpScores", result)
        self.assertIn("transformerScores", result)

    def test_description_fallback_when_five_w_empty(self):
        result = classify_report({
            "title": "Network outage",
            "description": "Core switch failure and network outage in the data center",
        })
        self.assertEqual(result["riskCategory"], "operational")
        self.assertEqual(result["responsibleDepartment"], "Information Technology")


if __name__ == "__main__":
    unittest.main()
