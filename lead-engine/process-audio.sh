#!/usr/bin/env bash
# V17.7 Jessica Audio Kit
# Requires ffmpeg. Optional: rnnoise/demucs/sox if installed.
# Usage:
# bash audio-kit/process-audio.sh input.mp4 output.wav

INPUT="$1"
OUTPUT="$2"

if [ -z "$INPUT" ] || [ -z "$OUTPUT" ]; then
  echo "Usage: process-audio.sh input output"
  exit 1
fi

# Social voice cleanup chain:
# highpass removes rumble, lowpass removes harsh upper junk,
# afftdn reduces noise, dynaudnorm levels, loudnorm targets social loudness.
ffmpeg -y -i "$INPUT" -vn \
-af "highpass=f=80,lowpass=f=12000,afftdn=nf=-25,dynaudnorm=f=150:g=15,acompressor=threshold=-18dB:ratio=3:attack=8:release=80,loudnorm=I=-16:TP=-1.5:LRA=11" \
-ar 48000 -ac 2 "$OUTPUT"
