(() => {
  'use strict';

  const root = document.querySelector('[data-test-data-tool]');
  if (!root) return;

  const encoder = new TextEncoder();
  const tabs = root.querySelectorAll('[data-tab]');
  const panels = root.querySelectorAll('[data-panel]');
  const fileForm = root.querySelector('#test-file-form');
  const fileKind = root.querySelector('#file-kind');
  const fileSize = root.querySelector('#file-size');
  const fileUnit = root.querySelector('#file-unit');
  const fileName = root.querySelector('#file-name');
  const dimension = root.querySelector('#image-dimension');
  const imageOption = root.querySelector('.file-image-option');
  const videoDuration = root.querySelector('#video-duration');
  const videoOption = root.querySelector('.file-video-option');
  const fileHint = root.querySelector('#file-hint');
  const fileStatus = root.querySelector('#file-status');
  const messageForm = root.querySelector('#test-message-form');
  const messageCount = root.querySelector('#message-count');
  const messagePreset = root.querySelector('#message-preset');
  const messagePrefix = root.querySelector('#message-prefix');
  const messageOutput = root.querySelector('#message-output');
  const messageCopy = root.querySelector('#message-copy');
  const messageStatus = root.querySelector('#message-status');
  const contactForm = root.querySelector('#test-contact-form');
  const contactCount = root.querySelector('#contact-count');
  const contactPrefix = root.querySelector('#contact-prefix');
  const contactPreview = root.querySelector('#contact-preview');
  const contactCsv = root.querySelector('#contact-csv');
  const contactStatus = root.querySelector('#contact-status');
  const maxBytes = 100 * 1024 * 1024;
  let contacts = [];

  const setStatus = (element, text, isError = false) => {
    element.textContent = text;
    element.classList.toggle('is-error', isError);
  };

  const formatBytes = (bytes) => bytes >= 1024 * 1024
    ? `${(bytes / (1024 * 1024)).toFixed(bytes % (1024 * 1024) === 0 ? 0 : 2)} MiB`
    : `${bytes.toLocaleString('ko-KR')} bytes`;

  const safeName = (value, fallback) => {
    const cleaned = value.trim().replace(/[\\/:*?"<>|\u0000-\u001f]/g, '-').replace(/^\.+|\.+$/g, '');
    return cleaned || fallback;
  };

  const download = (blob, name) => {
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = name;
    document.body.append(anchor);
    anchor.click();
    anchor.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
  };

  const concat = (parts) => {
    const length = parts.reduce((total, part) => total + part.length, 0);
    const output = new Uint8Array(length);
    let offset = 0;
    parts.forEach((part) => { output.set(part, offset); offset += part.length; });
    return output;
  };

  const u16 = (value) => Uint8Array.of(value & 255, (value >>> 8) & 255);
  const u32 = (value) => Uint8Array.of(value & 255, (value >>> 8) & 255, (value >>> 16) & 255, (value >>> 24) & 255);
  const be32 = (value) => Uint8Array.of((value >>> 24) & 255, (value >>> 16) & 255, (value >>> 8) & 255, value & 255);
  const ascii = (value) => encoder.encode(value);

  const crcTable = (() => {
    const table = new Uint32Array(256);
    for (let index = 0; index < 256; index += 1) {
      let value = index;
      for (let bit = 0; bit < 8; bit += 1) value = value & 1 ? (value >>> 1) ^ 0xedb88320 : value >>> 1;
      table[index] = value >>> 0;
    }
    return table;
  })();

  const crc32 = (data) => {
    let value = 0xffffffff;
    for (let index = 0; index < data.length; index += 1) value = (value >>> 8) ^ crcTable[(value ^ data[index]) & 255];
    return (value ^ 0xffffffff) >>> 0;
  };

  const zip = (entries) => {
    const localParts = [];
    const centralParts = [];
    let offset = 0;
    entries.forEach(({ name, data }) => {
      const nameBytes = ascii(name);
      const checksum = crc32(data);
      const local = concat([ascii('PK\x03\x04'), u16(20), u16(0), u16(0), u16(0), u16(0), u32(checksum), u32(data.length), u32(data.length), u16(nameBytes.length), u16(0), nameBytes, data]);
      localParts.push(local);
      const central = concat([ascii('PK\x01\x02'), u16(20), u16(20), u16(0), u16(0), u16(0), u16(0), u32(checksum), u32(data.length), u32(data.length), u16(nameBytes.length), u16(0), u16(0), u16(0), u16(0), u32(0), u32(offset), nameBytes]);
      centralParts.push(central);
      offset += local.length;
    });
    const central = concat(centralParts);
    const ending = concat([ascii('PK\x05\x06'), u16(0), u16(0), u16(entries.length), u16(entries.length), u32(central.length), u32(offset), u16(0)]);
    return concat([...localParts, central, ending]);
  };

  const paddedZip = (entries, target) => {
    const withEmptyPadding = [...entries, { name: 'padding.bin', data: new Uint8Array(0) }];
    const base = zip(withEmptyPadding);
    const padding = target - base.length;
    if (padding < 0) throw new Error(`선택한 용량이 문서 최소 크기(${formatBytes(base.length)})보다 작습니다.`);
    return zip([...entries, { name: 'padding.bin', data: new Uint8Array(padding) }]);
  };

  const xml = (value) => value.replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&apos;' })[character]);

  const makeXlsx = (target) => paddedZip([
    { name: '[Content_Types].xml', data: ascii('<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="bin" ContentType="application/octet-stream"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>') },
    { name: '_rels/.rels', data: ascii('<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>') },
    { name: 'xl/workbook.xml', data: ascii('<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Test data" sheetId="1" r:id="rId1"/></sheets></workbook>') },
    { name: 'xl/_rels/workbook.xml.rels', data: ascii('<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>') },
    { name: 'xl/worksheets/sheet1.xml', data: ascii('<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>ROSSI TEST DATA</t></is></c><c r="B1" t="inlineStr"><is><t>Generated locally</t></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>Target size</t></is></c><c r="B2" t="inlineStr"><is><t>' + xml(formatBytes(target)) + '</t></is></c></row></sheetData></worksheet>') },
  ], target);

  const makeDocx = (target) => paddedZip([
    { name: '[Content_Types].xml', data: ascii('<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="bin" ContentType="application/octet-stream"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>') },
    { name: '_rels/.rels', data: ascii('<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>') },
    { name: 'word/document.xml', data: ascii('<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>ROSSI TEST DOCUMENT</w:t></w:r></w:p><w:p><w:r><w:t>This file was generated locally for upload testing.</w:t></w:r></w:p><w:sectPr><w:pgSz w:w="11906" w:h="16838"/></w:sectPr></w:body></w:document>') },
  ], target);

  const buildPdf = (padding) => {
    const pageContent = 'BT /F1 18 Tf 72 760 Td (ROSSI TEST DOCUMENT) Tj ET\n';
    const objects = [
      '1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n',
      '2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n',
      '3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n',
      `4 0 obj\n<< /Length ${encoder.encode(pageContent).length} >>\nstream\n${pageContent}endstream\nendobj\n`,
      '5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n',
      `6 0 obj\n<< /Length ${padding} >>\nstream\n${'0'.repeat(padding)}\nendstream\nendobj\n`,
    ];
    let result = '%PDF-1.4\n%ROSSI\n';
    const offsets = [0];
    objects.forEach((object) => { offsets.push(result.length); result += object; });
    const xref = result.length;
    result += 'xref\n0 7\n0000000000 65535 f \n';
    offsets.slice(1).forEach((offset) => { result += `${String(offset).padStart(10, '0')} 00000 n \n`; });
    result += `trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF\n`;
    return encoder.encode(result);
  };

  const makePdf = (target) => {
    let padding = Math.max(0, target - 800);
    for (let attempts = 0; attempts < 8; attempts += 1) {
      const output = buildPdf(padding);
      const difference = target - output.length;
      if (difference === 0) return output;
      padding += difference;
    }
    throw new Error('PDF 크기를 맞추지 못했습니다. 다시 시도해 주세요.');
  };

  const makeText = (target) => {
    const seed = encoder.encode('ROSSI TEST TEXT FILE\nThis content is generated locally for upload testing.\n');
    const output = new Uint8Array(target);
    for (let offset = 0; offset < target; offset += seed.length) output.set(seed.subarray(0, Math.min(seed.length, target - offset)), offset);
    return output;
  };

  const pngChunk = (type, data) => {
    const typeBytes = ascii(type);
    return concat([be32(data.length), typeBytes, data, be32(crc32(concat([typeBytes, data])))]);
  };

  const makePng = async (target) => {
    const [width, height] = dimension.value.split('x').map(Number);
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const context = canvas.getContext('2d');
    const block = Math.max(24, Math.floor(width / 16));
    for (let y = 0; y < height; y += block) for (let x = 0; x < width; x += block) {
      context.fillStyle = (Math.floor(x / block) + Math.floor(y / block)) % 2 ? '#d7ff5f' : '#20241f';
      context.fillRect(x, y, block, block);
    }
    context.fillStyle = '#111310';
    context.font = `700 ${Math.max(24, Math.floor(width / 15))}px system-ui`;
    context.fillText('ROSSI TEST IMAGE', Math.floor(width * .08), Math.floor(height * .48));
    context.font = `500 ${Math.max(16, Math.floor(width / 32))}px system-ui`;
    context.fillText(formatBytes(target), Math.floor(width * .08), Math.floor(height * .58));
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
    if (!blob) throw new Error('PNG 이미지를 만들지 못했습니다.');
    const base = new Uint8Array(await blob.arrayBuffer());
    const remaining = target - base.length;
    if (remaining < 20) throw new Error(`선택한 용량이 이미지 최소 크기(${formatBytes(base.length + 20)})보다 작습니다.`);
    const padding = new Uint8Array(remaining - 12);
    padding.set(ascii('padding\0'));
    return concat([base.subarray(0, base.length - 12), pngChunk('tEXt', padding), base.subarray(base.length - 12)]);
  };

  const ebmlVint = (value) => {
    for (let bytes = 1; bytes <= 4; bytes += 1) {
      if (value <= (2 ** (7 * bytes)) - 2) {
        const output = new Uint8Array(bytes);
        let current = value;
        for (let index = bytes - 1; index >= 0; index -= 1) { output[index] = current & 255; current = Math.floor(current / 256); }
        output[0] |= 1 << (8 - bytes);
        return output;
      }
    }
    throw new Error('동영상 패딩 크기가 너무 큽니다.');
  };

  const webmPadding = (length) => {
    for (let vintLength = 1; vintLength <= 4; vintLength += 1) {
      const payloadLength = length - 1 - vintLength;
      if (payloadLength >= 0 && payloadLength <= (2 ** (7 * vintLength)) - 2) return concat([Uint8Array.of(0xec), ebmlVint(payloadLength), new Uint8Array(payloadLength)]);
    }
    throw new Error('동영상 패딩을 만들지 못했습니다.');
  };

  const openWebmSegment = (data) => {
    for (let index = 0; index <= data.length - 12; index += 1) {
      if (data[index] !== 0x18 || data[index + 1] !== 0x53 || data[index + 2] !== 0x80 || data[index + 3] !== 0x67) continue;
      const firstSizeByte = data[index + 4];
      let sizeLength = 1;
      while (sizeLength <= 8 && (firstSizeByte & (1 << (8 - sizeLength))) === 0) sizeLength += 1;
      if (sizeLength !== 8) throw new Error('이 브라우저의 WebM 구조는 정확한 용량 생성에 지원되지 않습니다. Chrome 또는 Edge를 사용해 주세요.');
      const output = data.slice();
      output[index + 4] = 0x01;
      output.fill(0xff, index + 5, index + 12);
      return output;
    }
    throw new Error('생성된 WebM의 영상 구간을 찾지 못했습니다. 다시 시도해 주세요.');
  };

  const verifyWebm = (data) => new Promise((resolve, reject) => {
    const url = URL.createObjectURL(new Blob([data], { type: 'video/webm' }));
    const video = document.createElement('video');
    const timeout = window.setTimeout(() => finish(new Error('생성된 WebM의 재생 정보를 확인하지 못했습니다.')), 5000);
    const finish = (error) => {
      window.clearTimeout(timeout);
      video.removeAttribute('src');
      URL.revokeObjectURL(url);
      if (error) reject(error); else resolve();
    };
    video.preload = 'metadata';
    video.onloadedmetadata = () => video.videoWidth > 0 && video.videoHeight > 0
      ? finish()
      : finish(new Error('영상 프레임이 없는 WebM이 생성되었습니다. 다시 시도해 주세요.'));
    video.onerror = () => finish(new Error('생성된 WebM을 브라우저에서 재생할 수 없습니다.'));
    video.src = url;
  });

  const makeWebm = async (target) => {
    if (!window.MediaRecorder || !HTMLCanvasElement.prototype.captureStream) throw new Error('이 브라우저는 WebM 동영상 생성을 지원하지 않습니다. Chrome 또는 Edge를 사용해 주세요.');
    const canvas = document.createElement('canvas');
    canvas.width = 640;
    canvas.height = 360;
    const context = canvas.getContext('2d');
    const drawFrame = (frame) => {
      context.fillStyle = '#20241f'; context.fillRect(0, 0, canvas.width, canvas.height);
      context.fillStyle = '#d7ff5f'; context.fillRect(0, 0, canvas.width, 72);
      context.fillStyle = '#111310'; context.font = '700 36px system-ui'; context.fillText('ROSSI TEST VIDEO', 42, 47);
      context.fillStyle = '#f5f3eb'; context.font = '500 24px system-ui'; context.fillText(formatBytes(target), 42, 150);
      context.fillStyle = '#a6aca1'; context.font = '500 16px ui-monospace, monospace'; context.fillText(`FRAME ${String(frame).padStart(3, '0')}`, 42, 205);
      context.fillStyle = frame % 2 ? '#ff8c78' : '#d7ff5f'; context.fillRect(42 + (frame % 12) * 42, 252, 30, 30);
    };
    const stream = canvas.captureStream(12);
    const mimeType = ['video/webm;codecs=vp9', 'video/webm;codecs=vp8', 'video/webm'].find((type) => MediaRecorder.isTypeSupported(type));
    if (!mimeType) throw new Error('이 브라우저에서 지원하는 WebM 코덱을 찾지 못했습니다. Chrome 또는 Edge를 사용해 주세요.');
    const options = { mimeType, videoBitsPerSecond: 700000 };
    const chunks = [];
    const recorder = new MediaRecorder(stream, options);
    const stopped = new Promise((resolve, reject) => { recorder.onstop = resolve; recorder.onerror = () => reject(new Error('WebM 동영상 생성에 실패했습니다.')); });
    recorder.ondataavailable = (event) => { if (event.data.size) chunks.push(event.data); };
    recorder.start();
    let frame = 0;
    drawFrame(frame);
    const frameTimer = window.setInterval(() => drawFrame(++frame), 80);
    await new Promise((resolve) => window.setTimeout(resolve, Number(videoDuration.value) * 1000));
    window.clearInterval(frameTimer);
    recorder.stop();
    await stopped;
    stream.getTracks().forEach((track) => track.stop());
    const base = openWebmSegment(new Uint8Array(await new Blob(chunks, { type: mimeType }).arrayBuffer()));
    await verifyWebm(base);
    const remaining = target - base.length;
    if (remaining < 3) throw new Error(`선택한 용량이 동영상 최소 크기(${formatBytes(base.length + 3)})보다 작습니다.`);
    const output = concat([base, webmPadding(remaining)]);
    await verifyWebm(output);
    return output;
  };

  const targetBytes = () => {
    const size = Number(fileSize.value);
    if (!Number.isFinite(size) || size < 1) throw new Error('용량은 1 이상으로 입력해 주세요.');
    const bytes = Math.round(size * (fileUnit.value === 'MiB' ? 1024 * 1024 : 1000 * 1000));
    if (bytes > maxBytes) throw new Error('브라우저 안정성을 위해 한 번에 최대 100 MiB까지만 만들 수 있습니다.');
    return bytes;
  };

  const updateFileOptions = () => {
    const isImage = fileKind.value === 'png';
    const isVideo = fileKind.value === 'webm';
    imageOption.hidden = !isImage;
    videoOption.hidden = !isVideo;
    fileHint.textContent = isVideo
      ? '선택한 길이만큼 브라우저에서 실제 프레임을 녹화한 WebM입니다. Chrome·Edge에서 가장 안정적입니다.'
      : '실제 파일 형식을 만든 뒤, 보이지 않는 패딩을 추가해 목표 바이트에 맞춥니다. 생성 파일은 서버에 저장되지 않습니다.';
  };

  tabs.forEach((tab) => tab.addEventListener('click', () => {
    tabs.forEach((item) => { const active = item === tab; item.classList.toggle('is-active', active); item.setAttribute('aria-selected', String(active)); });
    panels.forEach((panel) => { panel.hidden = panel.dataset.panel !== tab.dataset.tab; });
  }));

  fileKind.addEventListener('change', updateFileOptions);
  updateFileOptions();

  fileForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
      const target = targetBytes();
      const kind = fileKind.value;
      const button = fileForm.querySelector('button[type="submit"]');
      button.disabled = true;
      setStatus(fileStatus, '파일을 생성하고 있습니다…');
      let data;
      if (kind === 'png') data = await makePng(target);
      else if (kind === 'txt') data = makeText(target);
      else if (kind === 'pdf') data = makePdf(target);
      else if (kind === 'docx') data = makeDocx(target);
      else if (kind === 'xlsx') data = makeXlsx(target);
      else data = await makeWebm(target);
      if (data.length !== target) throw new Error(`목표 용량과 다릅니다 (${data.length.toLocaleString('ko-KR')} bytes).`);
      const mime = { png: 'image/png', txt: 'text/plain;charset=utf-8', pdf: 'application/pdf', docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', webm: 'video/webm' }[kind];
      download(new Blob([data], { type: mime }), `${safeName(fileName.value, 'test-file')}.${kind}`);
      setStatus(fileStatus, `${kind.toUpperCase()} 파일 ${formatBytes(data.length)}을 만들었습니다.`);
      button.disabled = false;
    } catch (error) {
      setStatus(fileStatus, error instanceof Error ? error.message : '파일을 생성하지 못했습니다.', true);
      const button = fileForm.querySelector('button[type="submit"]');
      button.disabled = false;
    }
  });

  const messageLength = (value) => Array.from(value).length;
  const makeMessage = () => {
    const count = Number(messageCount.value);
    if (!Number.isInteger(count) || count < 1 || count > 2000) throw new Error('문자 수는 1~2,000자로 입력해 주세요.');
    const output = Array.from(messagePrefix.value);
    const filler = Array.from('가나다라마바사아자차카타파하 0123456789');
    for (let index = 0; output.length < count; index += 1) output.push(filler[index % filler.length]);
    return output.slice(0, count).join('');
  };

  messagePreset.addEventListener('change', () => { if (messagePreset.value !== 'custom') messageCount.value = messagePreset.value; });
  messageForm.addEventListener('submit', (event) => {
    event.preventDefault();
    try {
      const value = makeMessage();
      messageOutput.value = value;
      setStatus(messageStatus, `${messageLength(value).toLocaleString('ko-KR')}자 · UTF-8 ${encoder.encode(value).length.toLocaleString('ko-KR')} bytes`);
    } catch (error) { setStatus(messageStatus, error instanceof Error ? error.message : '문자를 만들지 못했습니다.', true); }
  });
  messageCopy.addEventListener('click', async () => {
    if (!messageOutput.value) { setStatus(messageStatus, '먼저 문자를 만들어 주세요.', true); return; }
    try { await navigator.clipboard.writeText(messageOutput.value); setStatus(messageStatus, '클립보드에 복사했습니다.'); }
    catch (_) { messageOutput.select(); document.execCommand('copy'); setStatus(messageStatus, '클립보드에 복사했습니다.'); }
  });

  const firstNames = ['민준', '서연', '도윤', '지우', '하준', '서윤', '시우', '지민', '예준', '수아', '건우', '유진'];
  const lastNames = ['김', '이', '박', '최', '정', '강', '조', '윤', '장', '임'];
  const makeContacts = () => {
    const count = Number(contactCount.value);
    const prefix = contactPrefix.value.replace(/\D/g, '').slice(0, 3);
    if (!Number.isInteger(count) || count < 1 || count > 1000) throw new Error('연락처 수는 1~1,000개로 입력해 주세요.');
    if (prefix.length !== 3) throw new Error('전화번호 시작값은 세 자리 숫자로 입력해 주세요.');
    return Array.from({ length: count }, (_, index) => ({
      name: `${lastNames[index % lastNames.length]}${firstNames[index % firstNames.length]}`,
      phone: `${prefix}-${String(1000 + Math.floor(index / 10000)).padStart(4, '0')}-${String(index % 10000).padStart(4, '0')}`,
      email: `test.contact.${String(index + 1).padStart(4, '0')}@example.test`,
      company: `테스트 컴퍼니 ${(index % 12) + 1}`,
    }));
  };

  const showContacts = (items) => {
    contactPreview.replaceChildren();
    const table = document.createElement('table');
    const head = document.createElement('thead');
    const row = document.createElement('tr');
    ['이름', '전화번호', '이메일', '회사'].forEach((label) => { const cell = document.createElement('th'); cell.textContent = label; row.append(cell); });
    head.append(row); table.append(head);
    const body = document.createElement('tbody');
    items.slice(0, 50).forEach((item) => { const itemRow = document.createElement('tr'); [item.name, item.phone, item.email, item.company].forEach((value) => { const cell = document.createElement('td'); cell.textContent = value; itemRow.append(cell); }); body.append(itemRow); });
    table.append(body); contactPreview.append(table);
  };

  contactForm.addEventListener('submit', (event) => {
    event.preventDefault();
    try { contacts = makeContacts(); showContacts(contacts); contactCsv.disabled = false; setStatus(contactStatus, `${contacts.length.toLocaleString('ko-KR')}개의 가상 연락처를 만들었습니다. 미리보기는 최대 50개만 표시합니다.`); }
    catch (error) { setStatus(contactStatus, error instanceof Error ? error.message : '연락처를 만들지 못했습니다.', true); }
  });
  contactCsv.addEventListener('click', () => {
    if (contacts.length === 0) return;
    const quote = (value) => `"${value.replace(/"/g, '""')}"`;
    const rows = [['이름', '전화번호', '이메일', '회사'], ...contacts.map((item) => [item.name, item.phone, item.email, item.company])];
    download(new Blob(['\uFEFF', rows.map((row) => row.map(quote).join(',')).join('\r\n')], { type: 'text/csv;charset=utf-8' }), 'rossi-dummy-contacts.csv');
    setStatus(contactStatus, 'CSV 파일을 내려받았습니다.');
  });
})();
