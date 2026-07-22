#!/usr/bin/env bash
# V17.2 Jessica Render Kit
# Server-side FFmpeg render helper.
# Requires ffmpeg installed on server.
# Usage:
# bash render-kit/render-short.sh input.mp4 output.mp4 "CAPTION TEXT" "CTA TEXT" logo.png

INPUT="$1"
OUTPUT="$2"
CAPTION="$3"
CTA="$4"
LOGO="$5"

if [ -z "$INPUT" ] || [ -z "$OUTPUT" ]; then
  echo "Usage: render-short.sh input output caption cta logo"
  exit 1
fi

FONT="/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"
if [ ! -f "$FONT" ]; then
  FONT="/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf"
fi

TMP="/tmp/jessica_render_$(date +%s).mp4"

# Vertical crop/scale, punchy captions, end CTA card placeholder.
if [ -f "$LOGO" ]; then
  ffmpeg -y -i "$INPUT" -i "$LOGO" \
  -filter_complex "[0:v]scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,eq=contrast=1.06:saturation=1.08[v];[1:v]scale=180:-1[logo];[v][logo]overlay=W-w-40:H-h-40,drawtext=fontfile=${FONT}:text='${CAPTION}':fontcolor=white:fontsize=58:borderw=4:bordercolor=black:x=(w-text_w)/2:y=120,drawtext=fontfile=${FONT}:text='${CTA}':fontcolor=white:fontsize=42:borderw=4:bordercolor=black:x=(w-text_w)/2:y=h-220" \
  -c:v libx264 -preset veryfast -crf 22 -c:a aac -b:a 160k -movflags +faststart "$OUTPUT"
else
  ffmpeg -y -i "$INPUT" \
  -vf "scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,eq=contrast=1.06:saturation=1.08,drawtext=fontfile=${FONT}:text='${CAPTION}':fontcolor=white:fontsize=58:borderw=4:bordercolor=black:x=(w-text_w)/2:y=120,drawtext=fontfile=${FONT}:text='${CTA}':fontcolor=white:fontsize=42:borderw=4:bordercolor=black:x=(w-text_w)/2:y=h-220" \
  -c:v libx264 -preset veryfast -crf 22 -c:a aac -b:a 160k -movflags +faststart "$OUTPUT"
fi
