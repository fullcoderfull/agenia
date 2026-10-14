'use client';

import { useEffect, useRef, useState } from 'react';

// Prise de photo par webcam (ordinateur). Sur mobile, l'appareil photo natif est
// utilisé à la place (input « capture »), plus fiable et de meilleure qualité.
// La photo est rendue sous forme de fichier JPEG et suit le circuit des pièces jointes.
// Le flux vidéo est coupé dès la fermeture : la caméra ne reste jamais allumée.

export interface WebcamStrings {
  title: string;
  shoot: string;
  switchCam: string;
  cancel: string;
  denied: string;
  notFound: string;
  failed: string;
}

export function WebcamCapture({
  open,
  s,
  onCapture,
  onClose,
}: {
  open: boolean;
  s: WebcamStrings;
  onCapture: (file: File) => void;
  onClose: () => void;
}) {
  const videoRef = useRef<HTMLVideoElement | null>(null);
  const streamRef = useRef<MediaStream | null>(null);
  const [cams, setCams] = useState<string[]>([]);
  const [camIdx, setCamIdx] = useState(0);
  const [err, setErr] = useState('');
  const [ready, setReady] = useState(false);

  function stop() {
    streamRef.current?.getTracks().forEach((tr) => tr.stop());
    streamRef.current = null;
    setReady(false);
  }

  async function start(deviceId?: string) {
    stop();
    setErr('');
    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        audio: false,
        video: deviceId
          ? { deviceId: { exact: deviceId }, width: { ideal: 1280 }, height: { ideal: 720 } }
          : { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
      });
      streamRef.current = stream;
      if (videoRef.current) {
        videoRef.current.srcObject = stream;
        await videoRef.current.play().catch(() => undefined);
      }
      setReady(true);
      // La liste des caméras n'est complète qu'après l'autorisation.
      const devices = await navigator.mediaDevices.enumerateDevices();
      setCams(devices.filter((d) => d.kind === 'videoinput').map((d) => d.deviceId));
    } catch (e) {
      const name = e instanceof DOMException ? e.name : '';
      setErr(name === 'NotAllowedError' || name === 'SecurityError' ? s.denied : name === 'NotFoundError' || name === 'OverconstrainedError' ? s.notFound : s.failed);
    }
  }

  useEffect(() => {
    if (open) {
      if (!navigator.mediaDevices?.getUserMedia) {
        setErr(s.failed);
        return;
      }
      setCamIdx(0);
      start();
    } else {
      stop();
    }
    return stop;
  }, [open]);

  function switchCam() {
    if (cams.length < 2) return;
    const next = (camIdx + 1) % cams.length;
    setCamIdx(next);
    start(cams[next]);
  }

  function shoot() {
    const v = videoRef.current;
    if (!v || !v.videoWidth) return;
    const c = document.createElement('canvas');
    c.width = v.videoWidth;
    c.height = v.videoHeight;
    c.getContext('2d')?.drawImage(v, 0, 0, c.width, c.height);
    c.toBlob(
      (blob) => {
        if (!blob) {
          setErr(s.failed);
          return;
        }
        const stamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
        onCapture(new File([blob], `photo-${stamp}.jpg`, { type: 'image/jpeg' }));
        onClose();
      },
      'image/jpeg',
      0.9,
    );
  }

  if (!open) return null;

  return (
    <div className="webcam-scrim" role="dialog" aria-modal="true" aria-label={s.title} onClick={onClose}>
      <div className="webcam" onClick={(e: { stopPropagation: () => void }) => e.stopPropagation()}>
        <h3>📷 {s.title}</h3>
        {err ? (
          <p className="webcam-err" role="alert">
            {err}
          </p>
        ) : (
          <video ref={videoRef} className="webcam-video" playsInline muted autoPlay />
        )}
        <div className="webcam-actions">
          <button type="button" className="btn-quiet btn-sm" onClick={onClose}>
            {s.cancel}
          </button>
          {!err && cams.length > 1 && (
            <button type="button" className="btn-quiet btn-sm" onClick={switchCam}>
              🔄 {s.switchCam}
            </button>
          )}
          {!err && (
            <button type="button" className="btn-primary btn-sm" onClick={shoot} disabled={!ready}>
              {s.shoot}
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
