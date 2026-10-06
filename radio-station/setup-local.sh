#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
command -v node >/dev/null || { echo 'Install Node 22 or newer first.'; exit 1; }
command -v python3 >/dev/null || { echo 'Install Python 3 first.'; exit 1; }
node -e 'if(Number(process.versions.node.split(".")[0])<22)process.exit(1)' || { echo 'Node 22 or newer is required.'; exit 1; }
python3 - <<'PY'
import os,secrets
try:
    fd=os.open('.env',os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
except FileExistsError:
    print('Keeping existing private .env configuration.')
else:
    with os.fdopen(fd,'w') as f:f.write('SOURCE_TOKEN='+secrets.token_urlsafe(36)+'\nBIND_HOST=127.0.0.1\nPORT=8788\nMAX_LISTENERS=30\n')
    print('Created private station credentials. Token was not printed.')
PY
cat <<'TXT'
Ready. In terminal 1:
  cd radio-station
  node --env-file=.env server.mjs
In terminal 2, from the same directory:
  python3 transmit.py --list-devices
  python3 transmit.py --mic 0
Or broadcast an original recording:
  python3 transmit.py --file /path/to/your-recording.wav
Then open http://127.0.0.1:8788 and press Tune in.
FFmpeg is required for transmitting (on macOS: brew install ffmpeg).
Nothing has been started or published by this setup script.
TXT
