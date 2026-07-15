(() => {
  'use strict';

  const root = document.querySelector('[data-qr-tool]');
  if (!root) return;

  const form = document.querySelector('#qr-form');
  const kind = document.querySelector('#qr-kind');
  const data = document.querySelector('#qr-data');
  const level = document.querySelector('#qr-level');
  const size = document.querySelector('#qr-size');
  const foreground = document.querySelector('#qr-foreground');
  const background = document.querySelector('#qr-background');
  const help = document.querySelector('#qr-data-help');
  const status = document.querySelector('#qr-status');
  const preview = document.querySelector('#qr-preview');
  const downloadPng = document.querySelector('#qr-download-png');
  const downloadSvg = document.querySelector('#qr-download-svg');
  const svgNamespace = 'http://www.w3.org/2000/svg';
  const quietZone = 4;
  let currentSvg = null;
  let debounceId = 0;

  const setStatus = (message, isError = false) => {
    status.textContent = message;
    status.classList.toggle('is-error', isError);
  };

  const setDownloads = (enabled) => {
    downloadPng.disabled = !enabled;
    downloadSvg.disabled = !enabled;
  };

  const normaliseValue = () => {
    const value = data.value.trim();
    if (!value) throw new Error('QR 코드에 넣을 내용을 입력해 주세요.');
    if (kind.value !== 'url') return value;

    const candidate = /^[a-z][a-z\d+.-]*:/i.test(value) ? value : `https://${value}`;
    let parsed;
    try {
      parsed = new URL(candidate);
    } catch (_) {
      throw new Error('올바른 웹 링크를 입력해 주세요.');
    }
    if (!['http:', 'https:'].includes(parsed.protocol) || !parsed.hostname) {
      throw new Error('http 또는 https 웹 링크만 사용할 수 있습니다.');
    }
    return parsed.href;
  };

  const makeSvg = (qr) => {
    const count = qr.getModuleCount();
    const total = count + quietZone * 2;
    const svg = document.createElementNS(svgNamespace, 'svg');
    svg.setAttribute('xmlns', svgNamespace);
    svg.setAttribute('viewBox', `0 0 ${total} ${total}`);
    svg.setAttribute('role', 'img');
    svg.setAttribute('aria-label', '생성된 QR 코드');
    svg.setAttribute('shape-rendering', 'crispEdges');

    const bg = document.createElementNS(svgNamespace, 'rect');
    bg.setAttribute('width', String(total));
    bg.setAttribute('height', String(total));
    bg.setAttribute('fill', background.value);
    svg.append(bg);

    let pathData = '';
    for (let row = 0; row < count; row += 1) {
      for (let column = 0; column < count; column += 1) {
        if (qr.isDark(row, column)) {
          const x = column + quietZone;
          const y = row + quietZone;
          pathData += `M${x} ${y}h1v1h-1z`;
        }
      }
    }
    const modules = document.createElementNS(svgNamespace, 'path');
    modules.setAttribute('fill', foreground.value);
    modules.setAttribute('d', pathData);
    svg.append(modules);
    return svg;
  };

  const generate = () => {
    if (typeof window.qrcode !== 'function') {
      setStatus('QR 생성기를 불러오지 못했습니다. 페이지를 새로고침해 주세요.', true);
      return;
    }
    try {
      const value = normaliseValue();
      window.qrcode.stringToBytes = window.qrcode.stringToBytesFuncs['UTF-8'];
      const qr = window.qrcode(0, level.value);
      qr.addData(value, 'Byte');
      qr.make();
      currentSvg = makeSvg(qr);
      preview.replaceChildren(currentSvg.cloneNode(true));
      setDownloads(true);
      setStatus(`QR 코드가 생성되었습니다. ${qr.getModuleCount()} × ${qr.getModuleCount()} 모듈`);
    } catch (error) {
      currentSvg = null;
      preview.replaceChildren(Object.assign(document.createElement('span'), { textContent: 'QR' }));
      setDownloads(false);
      const message = error instanceof Error ? error.message : 'QR 코드를 생성할 수 없습니다.';
      setStatus(message.includes('code length overflow') ? '내용이 너무 깁니다. 텍스트를 줄이거나 오류 복원 수준을 낮춰 주세요.' : message, true);
    }
  };

  const queueGenerate = () => {
    window.clearTimeout(debounceId);
    if (!data.value.trim()) return;
    debounceId = window.setTimeout(generate, 180);
  };

  const updateKind = () => {
    const isUrl = kind.value === 'url';
    data.placeholder = isUrl ? 'https://example.com' : 'QR 코드에 담을 텍스트를 입력하세요';
    help.textContent = isUrl
      ? 'http 또는 https 링크를 입력하세요. example.com처럼 입력하면 https://를 붙입니다.'
      : '텍스트는 최대 2,000자까지 입력할 수 있습니다. 길수록 QR 코드가 복잡해집니다.';
    queueGenerate();
  };

  const triggerDownload = (blob, filename) => {
    const anchor = document.createElement('a');
    const url = URL.createObjectURL(blob);
    anchor.href = url;
    anchor.download = filename;
    document.body.append(anchor);
    anchor.click();
    anchor.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
  };

  form.addEventListener('submit', (event) => { event.preventDefault(); generate(); });
  form.addEventListener('reset', () => {
    window.setTimeout(() => {
      currentSvg = null;
      preview.replaceChildren(Object.assign(document.createElement('span'), { textContent: 'QR' }));
      setDownloads(false);
      setStatus('내용을 입력하면 미리보기가 생성됩니다.');
      updateKind();
    }, 0);
  });
  [data, level, size, foreground, background].forEach((element) => element.addEventListener('input', queueGenerate));
  kind.addEventListener('change', updateKind);

  downloadSvg.addEventListener('click', () => {
    if (!currentSvg) return;
    const source = new XMLSerializer().serializeToString(currentSvg);
    triggerDownload(new Blob([source], { type: 'image/svg+xml;charset=utf-8' }), 'rossi-qr-code.svg');
  });

  downloadPng.addEventListener('click', () => {
    if (!currentSvg) return;
    const outputSize = Number(size.value);
    const source = new XMLSerializer().serializeToString(currentSvg);
    const blob = new Blob([source], { type: 'image/svg+xml;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const image = new Image();
    image.onload = () => {
      const canvas = document.createElement('canvas');
      canvas.width = outputSize;
      canvas.height = outputSize;
      const context = canvas.getContext('2d');
      context.fillStyle = background.value;
      context.fillRect(0, 0, outputSize, outputSize);
      context.imageSmoothingEnabled = false;
      context.drawImage(image, 0, 0, outputSize, outputSize);
      URL.revokeObjectURL(url);
      canvas.toBlob((png) => {
        if (png) triggerDownload(png, `rossi-qr-code-${outputSize}.png`);
      }, 'image/png');
    };
    image.onerror = () => {
      URL.revokeObjectURL(url);
      setStatus('PNG 파일을 만들지 못했습니다. SVG 다운로드를 사용해 주세요.', true);
    };
    image.src = url;
  });
})();
