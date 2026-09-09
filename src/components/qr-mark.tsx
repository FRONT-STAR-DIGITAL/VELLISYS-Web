"use client";

import { useEffect, useState } from "react";
import QRCode from "qrcode";

export function QrMark({ payload, size = 96 }: { payload: string; size?: number }) {
  const [svg, setSvg] = useState<string>("");

  useEffect(() => {
    let alive = true;
    QRCode.toString(payload, {
      type: "svg",
      margin: 0,
      width: size,
      color: { dark: "#111111", light: "#ffffff" },
    }).then((out) => {
      if (alive) setSvg(out);
    });
    return () => {
      alive = false;
    };
  }, [payload, size]);

  if (!svg) {
    return (
      <div
        style={{ width: size, height: size, background: "#f3f3f3", border: "1px solid #ddd" }}
      />
    );
  }

  return (
    <div
      style={{ width: size, height: size }}
      dangerouslySetInnerHTML={{ __html: svg }}
    />
  );
}
