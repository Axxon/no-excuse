import re
from collections import Counter
from threading import Lock

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field
from presidio_analyzer import AnalyzerEngine, RecognizerResult
from presidio_analyzer.nlp_engine import NlpEngineProvider
from presidio_anonymizer import AnonymizerEngine
from presidio_anonymizer.entities import OperatorConfig


MODEL_NAME = "xx_ent_wiki_sm"
PSEUDONYMIZATION_VERSION = "presidio-spacy-v1"
MAX_TEXT_LENGTH = 200_000

nlp_configuration = {
    "nlp_engine_name": "spacy",
    "models": [{"lang_code": "fr", "model_name": MODEL_NAME}],
    "ner_model_configuration": {
        "model_to_presidio_entity_mapping": {
            "PER": "PERSON",
            "LOC": "LOCATION",
            "ORG": "ORGANIZATION",
            "MISC": "MISCELLANEOUS",
        },
        "low_confidence_score_multiplier": 0.4,
        "low_score_entity_names": ["MISCELLANEOUS"],
    },
}

nlp_engine = NlpEngineProvider(nlp_configuration=nlp_configuration).create_engine()
analyzer = AnalyzerEngine(nlp_engine=nlp_engine, supported_languages=["fr"])
anonymizer = AnonymizerEngine()
inference_lock = Lock()

api = FastAPI(
    title="no-excuse CV pseudonymizer",
    version="1.0.0",
    docs_url=None,
    redoc_url=None,
    openapi_url=None,
)


class AnonymizeRequest(BaseModel):
    text: str = Field(min_length=1, max_length=MAX_TEXT_LENGTH)
    candidate_name: str = Field(min_length=1, max_length=160)
    candidate_email: str = Field(min_length=3, max_length=255)
    professional_terms: list[str] = Field(default_factory=list, max_length=30)


class AnonymizeResponse(BaseModel):
    pseudonymized_text: str
    entity_counts: dict[str, int]
    model: str
    version: str


ENTITY_PATTERNS = {
    "EMAIL_ADDRESS": re.compile(r"(?<![\w.+-])[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}(?![\w.-])", re.IGNORECASE),
    "PHONE_NUMBER": re.compile(r"(?<!\w)(?:\+33|0033|0)[1-9](?:[ .()-]*\d{2}){4}(?!\w)"),
    "PERSONAL_URL": re.compile(r"\b(?:https?://|www\.)[^\s<>{}\[\]]+", re.IGNORECASE),
    "PERSONAL_ADDRESS": re.compile(
        r"(?<!\w)\d{1,4}(?:\s*(?:bis|ter))?\s+(?:rue|avenue|av\.?|boulevard|bd\.?|chemin|impasse|allée|place|quai)\s+[^,;]{2,80}?(?:\s*,?\s*\d{5}\s+[A-Za-zÀ-ÖØ-öø-ÿ' -]{2,50})?(?=\s{2,}|[;|]|$)",
        re.IGNORECASE,
    ),
    "DATE_OF_BIRTH": re.compile(
        r"\b(?:né(?:e)?\s+le|date\s+de\s+naissance\s*:?)[ ]*(?:\d{1,2}[/-]\d{1,2}[/-]\d{2,4}|\d{1,2}\s+[A-Za-zÀ-ÖØ-öø-ÿ]+\s+\d{4})",
        re.IGNORECASE,
    ),
}

OPERATORS = {
    "CANDIDATE_NAME": OperatorConfig("replace", {"new_value": "[CANDIDATE_NAME]"}),
    "CANDIDATE_EMAIL": OperatorConfig("replace", {"new_value": "[CANDIDATE_EMAIL]"}),
    "PERSON": OperatorConfig("replace", {"new_value": "[PERSON]"}),
    "LOCATION": OperatorConfig("replace", {"new_value": "[LOCATION]"}),
    "EMAIL_ADDRESS": OperatorConfig("replace", {"new_value": "[EMAIL]"}),
    "PHONE_NUMBER": OperatorConfig("replace", {"new_value": "[PHONE]"}),
    "PERSONAL_URL": OperatorConfig("replace", {"new_value": "[PERSONAL_URL]"}),
    "PERSONAL_ADDRESS": OperatorConfig("replace", {"new_value": "[PERSONAL_ADDRESS]"}),
    "DATE_OF_BIRTH": OperatorConfig("replace", {"new_value": "[DATE_OF_BIRTH]"}),
    "DEFAULT": OperatorConfig("replace", {"new_value": "[PERSONAL_DATA]"}),
}


@api.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok", "model": MODEL_NAME, "version": PSEUDONYMIZATION_VERSION}


@api.post("/anonymize", response_model=AnonymizeResponse)
def anonymize(payload: AnonymizeRequest) -> AnonymizeResponse:
    results = _known_identity_results(payload.text, payload.candidate_name, payload.candidate_email)
    results.extend(_pattern_results(payload.text))

    with inference_lock:
        nlp_results = analyzer.analyze(
                text=payload.text,
                language="fr",
                entities=["PERSON", "LOCATION"],
                score_threshold=0.35,
            )
        results.extend(
            result for result in nlp_results
            if not _is_protected_professional_term(payload.text[result.start:result.end], payload.professional_terms)
        )
        output = anonymizer.anonymize(text=payload.text, analyzer_results=results, operators=OPERATORS).text

    if _contains_known_identity(output, payload.candidate_name, payload.candidate_email):
        raise HTTPException(status_code=422, detail="Known candidate identity could not be removed")

    return AnonymizeResponse(
        pseudonymized_text=output,
        entity_counts=dict(Counter(result.entity_type for result in results)),
        model=MODEL_NAME,
        version=PSEUDONYMIZATION_VERSION,
    )


def _known_identity_results(text: str, candidate_name: str, candidate_email: str) -> list[RecognizerResult]:
    results = _matches(text, _literal_pattern(candidate_email), "CANDIDATE_EMAIL", 1.0)
    for pattern in _name_patterns(candidate_name):
        results.extend(_matches(text, pattern, "CANDIDATE_NAME", 1.0))
    return results


def _pattern_results(text: str) -> list[RecognizerResult]:
    results: list[RecognizerResult] = []
    for entity_type, pattern in ENTITY_PATTERNS.items():
        results.extend(_matches(text, pattern, entity_type, 0.85))
    return results


def _matches(text: str, pattern: re.Pattern[str], entity_type: str, score: float) -> list[RecognizerResult]:
    return [
        RecognizerResult(entity_type=entity_type, start=match.start(), end=match.end(), score=score)
        for match in pattern.finditer(text)
    ]


def _name_patterns(candidate_name: str) -> list[re.Pattern[str]]:
    tokens = [token for token in re.split(r"[^\wÀ-ÖØ-öø-ÿ'-]+", candidate_name.strip()) if len(token) >= 2]
    variants = [tokens]
    if len(tokens) > 1:
        variants.append(list(reversed(tokens)))
        variants.extend([[token] for token in tokens if len(token) >= 3])

    unique: dict[str, re.Pattern[str]] = {}
    for variant in variants:
        body = r"[\s'-]+".join(_accent_flexible(token) for token in variant)
        unique[body] = re.compile(rf"(?<!\w){body}(?!\w)", re.IGNORECASE)
    return list(unique.values())


def _accent_flexible(value: str) -> str:
    groups = {
        "a": "aàâäáãå", "c": "cç", "e": "eéèêë", "i": "iîïíì",
        "o": "oôöóòõ", "u": "uùûüú", "y": "yÿý",
    }
    parts = []
    for character in value:
        replacement = groups.get(character.casefold())
        parts.append(f"[{replacement}]" if replacement else re.escape(character))
    return "".join(parts)


def _literal_pattern(value: str) -> re.Pattern[str]:
    return re.compile(re.escape(value.strip()), re.IGNORECASE)


def _contains_known_identity(text: str, candidate_name: str, candidate_email: str) -> bool:
    if _literal_pattern(candidate_email).search(text):
        return True
    return any(pattern.search(text) for pattern in _name_patterns(candidate_name))


def _is_protected_professional_term(value: str, professional_terms: list[str]) -> bool:
    normalized = value.strip().casefold()
    return any(normalized == term.strip().casefold() for term in professional_terms if term.strip())
