import unittest

from app import AnonymizeRequest, anonymize, health


class PseudonymizerTest(unittest.TestCase):
    def test_masks_known_identity_and_french_personal_data(self) -> None:
        response = anonymize(AnonymizeRequest(
            text=(
                "DUPONT Jean, né le 02/03/1990. Jean Dupont est développeur Laravel. "
                "Contact: jean.dupont@example.fr ou 06 12 34 56 78. "
                "Portfolio https://linkedin.com/in/jean-dupont."
            ),
            candidate_name="Jean Dupont",
            candidate_email="jean.dupont@example.fr",
        ))

        self.assertNotIn("Jean Dupont", response.pseudonymized_text)
        self.assertNotIn("DUPONT Jean", response.pseudonymized_text)
        self.assertNotIn("jean.dupont@example.fr", response.pseudonymized_text)
        self.assertNotIn("06 12 34 56 78", response.pseudonymized_text)
        self.assertIn("Laravel", response.pseudonymized_text)
        self.assertIn("[CANDIDATE_NAME]", response.pseudonymized_text)
        self.assertIn("[CANDIDATE_EMAIL]", response.pseudonymized_text)

    def test_masks_accent_variants_without_rewriting_professional_content(self) -> None:
        response = anonymize(AnonymizeRequest(
            text="Elodie Noel maîtrise PHP, PostgreSQL et Docker depuis 2019.",
            candidate_name="Élodie Noël",
            candidate_email="elodie@example.test",
            professional_terms=["PHP", "PostgreSQL", "Docker"],
        ))

        self.assertNotIn("Elodie Noel", response.pseudonymized_text)
        self.assertIn("PHP, PostgreSQL et Docker depuis 2019", response.pseudonymized_text)

    def test_health_exposes_no_document_data(self) -> None:
        self.assertEqual({"status": "ok", "model": "xx_ent_wiki_sm"}, health())


if __name__ == "__main__":
    unittest.main()
