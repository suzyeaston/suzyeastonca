import { useEffect, useRef, useState } from 'preact/hooks';
import type { JSX } from 'preact';

const statusLines = ['VANCOUVER SYSTEMS ONLINE', 'SIGNAL LOCKED', 'INSERT COIN', 'BOOTING STRANGE MACHINES'];

export default function HeroHarbor(): JSX.Element {
  const mount = useRef<HTMLDivElement>(null);
  const [status, setStatus] = useState(statusLines[0]);

  useEffect(() => {
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const statusTimer = reduced ? undefined : window.setInterval(() => {
      setStatus((current) => statusLines[(statusLines.indexOf(current) + 1) % statusLines.length]);
    }, 1800);

    let destroyed = false;
    let app: any;
    let frame = 0;

    async function bootPixi() {
      if (reduced || !mount.current) return;
      const PIXI = await import('pixi.js');
      if (destroyed || !mount.current) return;
      app = new PIXI.Application();
      await app.init({ resizeTo: mount.current, backgroundAlpha: 0, antialias: true, autoDensity: true, resolution: Math.min(window.devicePixelRatio, 2) });
      mount.current.appendChild(app.canvas);

      const stage = app.stage;
      const skyline = new PIXI.Graphics();
      const orca = new PIXI.Graphics();
      const sweep = new PIXI.Graphics();
      stage.addChild(skyline, orca, sweep);

      const draw = () => {
        const w = app.screen.width;
        const h = app.screen.height;
        skyline.clear().moveTo(0, h * .48).lineTo(w * .11, h * .34).lineTo(w * .2, h * .43).lineTo(w * .32, h * .26).lineTo(w * .45, h * .48).lineTo(w * .58, h * .3).lineTo(w * .72, h * .5).lineTo(w, h * .37).lineTo(w, h).lineTo(0, h).fill({ color: 0x061525, alpha: .58 });
        for (let i = 0; i < 18; i++) skyline.rect(i * w / 18, h * (.58 - (i % 5) * .028), w / 26, h * .18).fill({ color: 0x0a2630, alpha: .82 });
        skyline.stroke({ color: 0x57f3ff, alpha: .26, width: 2 }).moveTo(0, h * .69).lineTo(w, h * .69);

        const ox = w * (.64 + Math.sin(frame / 90) * .045);
        const oy = h * (.56 + Math.cos(frame / 75) * .035);
        orca.clear().ellipse(ox, oy, w * .12, h * .045).fill(0xf7f0db).ellipse(ox - w * .025, oy - h * .012, w * .11, h * .04).fill(0x041018).poly([ox + w * .11, oy, ox + w * .18, oy - h * .05, ox + w * .16, oy, ox + w * .19, oy + h * .045]).fill(0x041018).poly([ox - w * .015, oy - h * .04, ox + w * .04, oy - h * .14, ox + w * .035, oy - h * .035]).fill(0x041018).circle(ox - w * .075, oy - h * .014, 3).fill(0x39ff14);

        sweep.clear().arc(w * .18, h * .24, Math.min(w, h) * .36, frame / 80, frame / 80 + .5).stroke({ color: 0x39ff14, alpha: .25, width: 2 });
        frame += 1;
      };
      app.ticker.add(draw);
    }

    bootPixi();
    return () => { destroyed = true; if (statusTimer) clearInterval(statusTimer); if (app) app.destroy(true); };
  }, []);

  return <div class="hero-pixi" ref={mount} aria-hidden="true"><span class="hero-status">{status}</span></div>;
}
