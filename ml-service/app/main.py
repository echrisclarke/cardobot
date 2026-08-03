"""Card-o-Bot ML sidecar: embeddings, similarity, light safety."""

from __future__ import annotations

import json
import os
import time
from pathlib import Path
from typing import Any

import numpy as np
from fastapi import Depends, FastAPI, Header, HTTPException
from pydantic import BaseModel, Field

APP = FastAPI(title="Card-o-Bot ML", version="1.0.0")

DATA_DIR = Path(os.environ.get("ML_DATA_DIR", "/data/ml"))
STORE_PATH = DATA_DIR / "embeddings.json"
MODEL_NAME = os.environ.get("ML_EMBED_MODEL", "sentence-transformers/all-MiniLM-L6-v2")
TOKEN = os.environ.get("ML_SERVICE_TOKEN", "")

_model = None
_store: dict[str, Any] = {"cards": {}}


def _auth(authorization: str | None = Header(default=None)) -> None:
    if not TOKEN:
        return
    if not authorization or not authorization.startswith("Bearer "):
        raise HTTPException(status_code=401, detail="Unauthorized")
    if authorization.removeprefix("Bearer ").strip() != TOKEN:
        raise HTTPException(status_code=401, detail="Unauthorized")


def get_model():
    global _model
    if _model is None:
        from sentence_transformers import SentenceTransformer

        cache = os.environ.get("SENTENCE_TRANSFORMERS_HOME", str(DATA_DIR / "models"))
        os.makedirs(cache, exist_ok=True)
        _model = SentenceTransformer(MODEL_NAME, cache_folder=cache)
    return _model


def load_store() -> None:
    global _store
    DATA_DIR.mkdir(parents=True, exist_ok=True)
    if STORE_PATH.exists():
        try:
            _store = json.loads(STORE_PATH.read_text(encoding="utf-8"))
        except Exception:
            _store = {"cards": {}}
    if "cards" not in _store:
        _store["cards"] = {}


def save_store() -> None:
    DATA_DIR.mkdir(parents=True, exist_ok=True)
    STORE_PATH.write_text(json.dumps(_store), encoding="utf-8")


@APP.on_event("startup")
def startup() -> None:
    load_store()


class EmbedIn(BaseModel):
    texts: list[str] = Field(default_factory=list)


class SimilarIn(BaseModel):
    user_id: int
    text: str
    k: int = 3


class IndexIn(BaseModel):
    user_id: int
    card_id: str
    text: str
    image_url: str | None = None
    nickname: str = ""


class SafetyIn(BaseModel):
    text: str


@APP.get("/health")
def health(_: None = Depends(_auth)) -> dict:
    return {"ok": True, "model": MODEL_NAME, "cards_indexed": len(_store.get("cards", {}))}


@APP.post("/embed")
def embed(body: EmbedIn, _: None = Depends(_auth)) -> dict:
    if not body.texts:
        return {"vectors": []}
    model = get_model()
    vecs = model.encode(body.texts, normalize_embeddings=True)
    return {"vectors": [v.tolist() for v in np.asarray(vecs)]}


@APP.post("/index_card")
def index_card(body: IndexIn, _: None = Depends(_auth)) -> dict:
    model = get_model()
    vec = model.encode([body.text or body.card_id], normalize_embeddings=True)[0]
    _store["cards"][body.card_id] = {
        "user_id": body.user_id,
        "card_id": body.card_id,
        "nickname": body.nickname,
        "text": body.text,
        "image_url": body.image_url,
        "vector": np.asarray(vec).tolist(),
        "updated_at": int(time.time()),
    }
    save_store()
    return {"ok": True, "card_id": body.card_id}


@APP.post("/similar")
def similar(body: SimilarIn, _: None = Depends(_auth)) -> dict:
    text = (body.text or "").strip()
    if not text:
        return {"matches": []}
    model = get_model()
    q = np.asarray(model.encode([text], normalize_embeddings=True)[0])
    matches = []
    for card in _store.get("cards", {}).values():
        if int(card.get("user_id", -1)) != int(body.user_id):
            continue
        v = np.asarray(card.get("vector") or [], dtype=float)
        if v.size == 0:
            continue
        score = float(np.dot(q, v))
        matches.append(
            {
                "card_id": card.get("card_id"),
                "nickname": card.get("nickname") or "",
                "score": score,
            }
        )
    matches.sort(key=lambda m: m["score"], reverse=True)
    return {"matches": matches[: max(1, min(body.k, 10))]}


@APP.post("/safety_check")
def safety_check(body: SafetyIn, _: None = Depends(_auth)) -> dict:
    """Lightweight keyword gate. OpenAI moderation can be layered later."""
    text = (body.text or "").lower()
    blocked = [
        "child porn",
        "csam",
        "gore porn",
        "bestiality",
    ]
    hits = [w for w in blocked if w in text]
    return {"safe": len(hits) == 0, "categories": hits}
