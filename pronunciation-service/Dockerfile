FROM python:3.11-slim

# ffmpeg: reading webm/mp3 uploads (pydub, and OpenPronounce's own audio
# loader). espeak-ng: OpenPronounce phonemizes the target text through it.
RUN apt-get update && apt-get install -y --no-install-recommends \
    ffmpeg \
    espeak-ng \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY main.py .

EXPOSE 8001

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8001"]
