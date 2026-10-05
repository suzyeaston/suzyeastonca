#!/usr/bin/env python3
"""Encode your mic or original recording and stream MP3 to our relay. No packages."""
import argparse
import http.client
import os
from pathlib import Path
import subprocess
import sys
from urllib.parse import urlsplit


def read_settings():
    settings={}
    path=Path(__file__).with_name('.env')
    if path.exists():
        for line in path.read_text().splitlines():
            if '=' in line and not line.lstrip().startswith('#'):
                key,value=line.split('=',1);settings[key.strip()]=value.strip()
    settings.update(os.environ)
    return settings


def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument('--server',default='http://127.0.0.1:8788',help='Relay origin. HTTPS required except loopback.')
    source=p.add_mutually_exclusive_group(required=True)
    source.add_argument('--file',type=Path,help='An original recording or music file you may broadcast')
    source.add_argument('--mic',help='macOS AVFoundation device index (e.g. 0), or Linux ALSA name (e.g. default)')
    source.add_argument('--list-devices',action='store_true')
    args=p.parse_args()
    if args.list_devices:
        command=['ffmpeg','-hide_banner','-f','avfoundation','-list_devices','true','-i',''] if sys.platform=='darwin' else ['arecord','-l']
        subprocess.run(command,check=False);return 0
    settings=read_settings();token=settings.get('SOURCE_TOKEN','')
    if len(token)<32:raise ValueError('Run setup-local.sh or set SOURCE_TOKEN in your private radio-station/.env file.')
    url=urlsplit(args.server)
    if url.username or url.password or url.query or url.fragment or url.path not in ('','/') or not url.hostname:raise ValueError('Use the relay origin only, with no credentials or path.')
    if url.scheme!='https' and not(url.scheme=='http' and url.hostname in ('localhost','127.0.0.1','::1')):raise ValueError('Use HTTPS for a remote relay. HTTP is allowed only on loopback.')
    connection=(http.client.HTTPSConnection if url.scheme=='https' else http.client.HTTPConnection)(url.hostname,url.port,timeout=15)
    command=['ffmpeg','-hide_banner','-loglevel','error','-nostdin']
    if args.file:
        if not args.file.is_file():raise ValueError('Audio file not found')
        command+=['-re','-i',str(args.file.resolve())]
    elif sys.platform=='darwin':command+=['-f','avfoundation','-i',':'+args.mic]
    elif sys.platform.startswith('linux'):command+=['-f','alsa','-i',args.mic]
    else:raise ValueError('Microphone mode supports macOS and Linux; use --file on other systems.')
    command+=['-vn','-ac','2','-ar','44100','-c:a','libmp3lame','-b:a','128k','-write_xing','0','-id3v2_version','0','-flush_packets','1','-f','mp3','pipe:1']
    process=None
    try:
        connection.putrequest('POST','/source');connection.putheader('Authorization','Bearer '+token);connection.putheader('Content-Type','audio/mpeg');connection.putheader('Transfer-Encoding','chunked');connection.endheaders()
        response=connection.getresponse()
        if response.status!=200:raise RuntimeError('Relay refused the source: HTTP '+str(response.status))
        print('Transmitting your audio. Ctrl-C ends the show. Listener path: /live.mp3',flush=True)
        process=subprocess.Popen(command,stdout=subprocess.PIPE,bufsize=0)
        while True:
            block=process.stdout.read(4096)
            if not block:break
            connection.send(f'{len(block):X}\r\n'.encode()+block+b'\r\n')
        connection.send(b'0\r\n\r\n');response.read()
        if process.wait()!=0:raise RuntimeError('FFmpeg stopped with an error. Check the audio device/file.')
    finally:
        connection.close()
        if process and process.poll() is None:
            process.terminate()
            try:process.wait(timeout=3)
            except subprocess.TimeoutExpired:process.kill();process.wait()
    return 0

if __name__=='__main__':
    try:sys.exit(main())
    except KeyboardInterrupt:print('\nBroadcast stopped.');sys.exit(130)
    except (ValueError,RuntimeError,OSError,http.client.HTTPException) as error:
        print('Transmitter stopped: '+str(error),file=sys.stderr);sys.exit(1)
