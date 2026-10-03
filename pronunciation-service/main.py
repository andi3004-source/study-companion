"""FastAPI microservice wrapping OpenPronounce (English) and tone-me's tone
model (Mandarin) for App\\Services\\MlPronunciationChecker in the Laravel app.

Both model stacks are loaded once at startup via `lifespan()`, never per
request — loading checkpoints is the expensive part (seconds, plus a one-time
download from the Hub on first run); paying that cost on every request would
blow the <3s response budget the Laravel side expects.

Endpoints mirror what MlPronunciationChecker (app/Services/MlPronunciationChecker.php)
calls: POST /check/english and POST /check/mandarin, both multipart form-data
with a `file` (wav/webm) and a `target` field.
"""

import logging
import os
import tempfile
from contextlib import asynccontextmanager

import librosa
import numpy as np
import pinyin_jyutping_sentence
import soundfile as sf
import torch
from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from pydub import AudioSegment
from transformers import AutoFeatureExtractor, AutoModelForAudioClassification

from openpronounce import compare_audio_with_text, load_audio

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("pronunciation-service")

# cge7/wav2vec2-base-version3: the tone-me model from
# https://github.com/tone-me/tone-me (MIT), reusing the inference logic of
# its app.py (evaluate_model()) directly instead of running that Flask app
# as a second process. Downloads automatically from the Hub on first load.
TONE_MODEL_NAME = "cge7/wav2vec2-base-version3"
TONE_SAMPLING_RATE = 16000

# Set once by _load_tone_model() at startup; read (never reassigned) by every
# /check/mandarin request afterwards.
_tone_model = None
_tone_feature_extractor = None


def _load_tone_model() -> None:
    global _tone_model, _tone_feature_extractor
    logger.info("Loading tone-me model %s", TONE_MODEL_NAME)
    _tone_model = AutoModelForAudioClassification.from_pretrained(TONE_MODEL_NAME)
    _tone_model.eval()
    _tone_feature_extractor = AutoFeatureExtractor.from_pretrained(TONE_MODEL_NAME)


def _warmup() -> None:
    """Run one dummy request through both code paths so weight loading and
    any first-call framework overhead happen now, not on the first real
    request."""
    silence_path = tempfile.mktemp(suffix=".wav")
    sf.write(silence_path, np.zeros(TONE_SAMPLING_RATE, dtype=np.float32), TONE_SAMPLING_RATE)
    try:
        compare_audio_with_text(load_audio(silence_path), "hello", lang="en")
        _predict_tone(silence_path)
    except Exception:
        logger.exception("warmup inference failed (models are loaded; a warmup call itself errored)")
    finally:
        os.remove(silence_path)


@asynccontextmanager
async def lifespan(app: FastAPI):
    _load_tone_model()
    _warmup()
    logger.info("pronunciation-service ready")
    yield


app = FastAPI(title="pronunciation-service", lifespan=lifespan)


def _save_upload(file: UploadFile) -> str:
    suffix = os.path.splitext(file.filename or "")[1] or ".webm"
    fd, path = tempfile.mkstemp(suffix=suffix, prefix="pronunciation-upload-")
    with os.fdopen(fd, "wb") as f:
        f.write(file.file.read())
    return path


def _to_wav(path: str) -> str:
    """Normalize any ffmpeg-readable upload (webm, mp3, wav, ...) to 16kHz mono wav."""
    if path.lower().endswith(".wav"):
        return path
    wav_path = path + ".wav"
    AudioSegment.from_file(path).set_frame_rate(TONE_SAMPLING_RATE).set_channels(1).export(wav_path, format="wav")
    return wav_path


# --- English (OpenPronounce) ------------------------------------------------

@app.post("/check/english")
async def check_english(file: UploadFile = File(...), target: str = Form(...)):
    upload_path = _save_upload(file)
    wav_path = None
    try:
        wav_path = _to_wav(upload_path)
        result = compare_audio_with_text(load_audio(wav_path), target, lang="en")

        differences = result.get("differences", {})
        errors = differences.get("errors", [])

        return {
            "is_correct": len(errors) == 0,
            "score": float(result.get("score", 0.0)),
            "heard_phonemes": " ".join(differences.get("heard_phones", [])),
            "target_phonemes": " ".join(differences.get("expected_phones", [])),
            "mispronounced_words": [e["word"] for e in errors],
        }
    except Exception:
        logger.exception("english pronunciation check failed")
        raise HTTPException(status_code=500, detail="pronunciation analysis failed")
    finally:
        if wav_path and wav_path != upload_path and os.path.exists(wav_path):
            os.remove(wav_path)
        os.remove(upload_path)


# --- Mandarin (tone-me) ------------------------------------------------

def _extract_target_tone(target_text: str) -> int:
    """First tone number (1-5) pinyin_jyutping_sentence finds for `target_text` —
    same lookup tone-me's own /fetch_text route (app.py: extract_tones()) falls
    back to when no tone is supplied manually. `target` is expected to be a
    single Hanzi character."""
    pinyin = pinyin_jyutping_sentence.pinyin(target_text, tone_numbers=True)
    for ch in pinyin:
        if ch in "12345":
            return int(ch)
    raise ValueError(f"could not determine a tone for {target_text!r}")


def _predict_tone(wav_path: str) -> int:
    """Same inference as tone-me's own evaluate_model() in app.py: load the
    clip, extract features, argmax the logits. tone-me's frontend splits a
    multi-syllable recording into per-character chunks before calling this;
    this service only ever receives one character's audio at a time, so no
    splitting step is needed here."""
    waveform, rate = librosa.load(wav_path, sr=TONE_SAMPLING_RATE)
    inputs = _tone_feature_extractor(waveform, sampling_rate=rate, return_tensors="pt").input_values
    with torch.no_grad():
        logits = _tone_model(inputs).logits
    return int(torch.argmax(logits, dim=-1).item())


@app.post("/check/mandarin")
async def check_mandarin(file: UploadFile = File(...), target: str = Form(...)):
    upload_path = _save_upload(file)
    wav_path = None
    try:
        wav_path = _to_wav(upload_path)
        predicted_tone = _predict_tone(wav_path)
        target_tone = _extract_target_tone(target)

        return {
            "is_correct": predicted_tone == target_tone,
            "predicted_tone": predicted_tone,
            "target_tone": target_tone,
        }
    except ValueError as e:
        raise HTTPException(status_code=422, detail=str(e))
    except Exception:
        logger.exception("mandarin pronunciation check failed")
        raise HTTPException(status_code=500, detail="pronunciation analysis failed")
    finally:
        if wav_path and wav_path != upload_path and os.path.exists(wav_path):
            os.remove(wav_path)
        os.remove(upload_path)


@app.get("/health")
async def health():
    return {"status": "ok"}
