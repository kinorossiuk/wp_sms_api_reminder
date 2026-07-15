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
  const maxBytes = 300 * 1024 * 1024;
  const webmBitrate = 12_000_000;
  let contacts = [];

  const setStatus = (element, text, isError = false) => {
    element.textContent = text;
    element.classList.toggle('is-error', isError);
  };

  const formatBytes = (bytes) => bytes >= 1024 * 1024
    ? `${(bytes / (1024 * 1024)).toFixed(bytes % (1024 * 1024) === 0 ? 0 : 2)} MiB`
    : `${bytes.toLocaleString('ko-KR')} bytes`;

  const formatDuration = (seconds) => seconds >= 60
    ? `${Math.floor(seconds / 60)}분 ${seconds % 60}초`
    : `${seconds}초`;

  const webmDurationForTarget = (target) => Math.max(1, Math.ceil((target * 8) / webmBitrate));

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

  const randomIndex = (length) => {
    if (window.crypto?.getRandomValues) {
      const value = new Uint32Array(1);
      window.crypto.getRandomValues(value);
      return value[0] % length;
    }
    return Math.floor(Math.random() * length);
  };

  const documentPatterns = [
    { name: '요약 보고서', title: 'SUMMARY REPORT', subtitle: 'Upload validation overview', accent: 'D7FF5F', rows: [['Status', 'Ready'], ['Files', '128'], ['Owner', 'QA Team'], ['Updated', '2026-07-15']] },
    { name: '재고 목록', title: 'INVENTORY LIST', subtitle: 'Sample warehouse records', accent: '78DCE8', rows: [['Item', 'Quantity'], ['Keyboard', '42'], ['Monitor', '18'], ['Cable', '256']] },
    { name: '주문 내역', title: 'ORDER LEDGER', subtitle: 'Fictional transaction data', accent: 'FFB86C', rows: [['Order', 'Amount'], ['TEST-1001', '12500'], ['TEST-1002', '8800'], ['TEST-1003', '31700']] },
    { name: '일정표', title: 'PROJECT SCHEDULE', subtitle: 'Milestone test document', accent: 'BD93F9', rows: [['Phase', 'Date'], ['Planning', '2026-07-01'], ['Testing', '2026-07-15'], ['Release', '2026-07-31']] },
    { name: '점검표', title: 'QUALITY CHECKLIST', subtitle: 'Synthetic inspection results', accent: '50FA7B', rows: [['Check', 'Result'], ['File name', 'PASS'], ['MIME type', 'PASS'], ['Size limit', 'PASS']] },
    { name: '회의 기록', title: 'MEETING NOTES', subtitle: 'Generated discussion outline', accent: 'FF79C6', rows: [['Topic', 'Decision'], ['Scope', 'Approved'], ['Risk', 'Reviewed'], ['Next step', 'Retest']] },
    { name: '설문 결과', title: 'SURVEY RESULTS', subtitle: 'Anonymous sample responses', accent: '8BE9FD', rows: [['Choice', 'Votes'], ['Option A', '37'], ['Option B', '29'], ['Option C', '14']] },
    { name: '시스템 로그', title: 'SYSTEM LOG', subtitle: 'Non-production event sample', accent: 'F1FA8C', rows: [['Time', 'Event'], ['09:00:01', 'START'], ['09:00:03', 'UPLOAD'], ['09:00:04', 'SUCCESS']] },
    { name: '연락처 표', title: 'CONTACT DIRECTORY', subtitle: 'Fictional test identities', accent: 'FF8C78', rows: [['Name', 'Extension'], ['Test User A', '1001'], ['Test User B', '1002'], ['Test User C', '1003']] },
    { name: '월간 지표', title: 'MONTHLY METRICS', subtitle: 'Synthetic performance figures', accent: 'A4FFFF', rows: [['Metric', 'Value'], ['Uploads', '1,240'], ['Validated', '1,198'], ['Rejected', '42']] },
  ];

  const xlsxColumn = (index) => String.fromCharCode(65 + index);
  const xlsxRows = (pattern) => [
    [pattern.title, pattern.subtitle],
    ...pattern.rows,
  ].map((row, rowIndex) => `<row r="${rowIndex + 1}"${rowIndex === 0 ? ' ht="28" customHeight="1"' : ''}>${row.map((value, columnIndex) => `<c r="${xlsxColumn(columnIndex)}${rowIndex + 1}" t="inlineStr" s="${rowIndex === 0 ? 1 : rowIndex === 1 ? 2 : 0}"><is><t>${xml(value)}</t></is></c>`).join('')}</row>`).join('');

  const makeXlsx = (target, pattern) => paddedZip([
    { name: '[Content_Types].xml', data: ascii('<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="bin" ContentType="application/octet-stream"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>') },
    { name: '_rels/.rels', data: ascii('<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>') },
    { name: 'xl/workbook.xml', data: ascii(`<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="${xml(pattern.name)}" sheetId="1" r:id="rId1"/></sheets></workbook>`) },
    { name: 'xl/_rels/workbook.xml.rels', data: ascii('<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>') },
    { name: 'xl/styles.xml', data: ascii(`<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Arial"/></font><font><b/><sz val="14"/><color rgb="FF111310"/><name val="Arial"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF${pattern.accent}"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="0" fontId="0" fillId="2" borderId="0" xfId="0" applyFill="1"/></cellXfs></styleSheet>`) },
    { name: 'xl/worksheets/sheet1.xml', data: ascii(`<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols><col min="1" max="1" width="24" customWidth="1"/><col min="2" max="2" width="34" customWidth="1"/></cols><sheetData>${xlsxRows(pattern)}<row r="7"><c r="A7" t="inlineStr"><is><t>Target size</t></is></c><c r="B7" t="inlineStr"><is><t>${xml(formatBytes(target))}</t></is></c></row></sheetData></worksheet>`) },
  ], target);

  const docxParagraphs = (pattern) => pattern.rows.map(([label, value], index) => `<w:p><w:pPr><w:spacing w:before="${index === 0 ? 240 : 80}" w:after="80"/><w:shd w:fill="${index % 2 ? 'F2F2F2' : 'FFFFFF'}"/></w:pPr><w:r><w:rPr><w:b/><w:color w:val="${pattern.accent}"/></w:rPr><w:t>${xml(label)}: </w:t></w:r><w:r><w:t>${xml(value)}</w:t></w:r></w:p>`).join('');

  const makeDocx = (target, pattern, patternIndex) => paddedZip([
    { name: '[Content_Types].xml', data: ascii('<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="bin" ContentType="application/octet-stream"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>') },
    { name: '_rels/.rels', data: ascii('<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>') },
    { name: 'word/document.xml', data: ascii(`<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:pPr><w:jc w:val="${patternIndex % 2 ? 'left' : 'center'}"/><w:spacing w:after="180"/><w:shd w:fill="${pattern.accent}"/></w:pPr><w:r><w:rPr><w:b/><w:sz w:val="34"/><w:color w:val="111310"/></w:rPr><w:t>${xml(pattern.title)}</w:t></w:r></w:p><w:p><w:r><w:rPr><w:i/><w:color w:val="666666"/></w:rPr><w:t>${xml(pattern.subtitle)}</w:t></w:r></w:p>${docxParagraphs(pattern)}<w:p><w:r><w:t>Target size: ${xml(formatBytes(target))}</w:t></w:r></w:p><w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/></w:sectPr></w:body></w:document>`) },
  ], target);

  const pdfEscape = (value) => value.replace(/[\\()]/g, '\\$&');
  const pdfPatternContent = (pattern, patternIndex, target) => {
    const accent = [parseInt(pattern.accent.slice(0, 2), 16), parseInt(pattern.accent.slice(2, 4), 16), parseInt(pattern.accent.slice(4, 6), 16)].map((value) => (value / 255).toFixed(3));
    const shapes = [
      '54 700 487 90 re f', '54 700 18 90 re f', '54 760 487 30 re f', '54 700 487 8 re f',
      '54 700 m 541 790 l 541 700 l h f', '54 700 90 90 re f', '54 700 487 90 re S',
      '54 745 487 45 re f', '54 700 240 90 re f', '54 700 487 90 re f 66 712 463 66 re S',
    ];
    const rows = pattern.rows.map(([label, value], index) => `BT /F1 11 Tf 72 ${640 - index * 42} Td (${pdfEscape(label)}) Tj 180 0 Td (${pdfEscape(value)}) Tj ET`).join('\n');
    return `q ${accent.join(' ')} rg ${shapes[patternIndex]} Q\nBT /F1 20 Tf 72 748 Td (${pdfEscape(pattern.title)}) Tj ET\nBT /F1 10 Tf 72 720 Td (${pdfEscape(pattern.subtitle)}) Tj ET\n${rows}\nBT /F1 9 Tf 72 430 Td (Target size: ${pdfEscape(formatBytes(target))}) Tj ET\n`;
  };

  const buildPdf = (padding, pattern, patternIndex, target) => {
    const pageContent = pdfPatternContent(pattern, patternIndex, target);
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

  const makePdf = (target, pattern, patternIndex) => {
    let padding = Math.max(0, target - 800);
    for (let attempts = 0; attempts < 8; attempts += 1) {
      const output = buildPdf(padding, pattern, patternIndex, target);
      const difference = target - output.length;
      if (difference === 0) return output;
      padding += difference;
    }
    throw new Error('PDF 크기를 맞추지 못했습니다. 다시 시도해 주세요.');
  };

  const textPatterns = [
    (pattern) => `${pattern.title}\n${pattern.subtitle}\n${pattern.rows.map((row) => row.join(': ')).join('\n')}\n`,
    (pattern) => `label,value\n${pattern.rows.map((row) => row.map((value) => `"${value}"`).join(',')).join('\n')}\n`,
    (pattern) => `${pattern.rows.map(([key, value]) => JSON.stringify({ key, value, test: true })).join('\n')}\n`,
    (pattern) => `${pattern.rows.map(([event, value], index) => `2026-07-15T09:00:0${index}Z INFO ${event.toUpperCase().replace(/ /g, '_')} value=${value}`).join('\n')}\n`,
    (pattern) => `[test-data]\n${pattern.rows.map(([key, value]) => `${key.toLowerCase().replace(/ /g, '_')}=${value}`).join('\n')}\n`,
    (pattern) => `${pattern.title}\n${'-'.repeat(48)}\n${pattern.rows.map(([key, value]) => `${key.padEnd(24)}${value.padStart(20)}`).join('\n')}\n`,
    (pattern) => `# ${pattern.title}\n\n${pattern.subtitle}\n\n${pattern.rows.map(([key, value]) => `- **${key}**: ${value}`).join('\n')}\n`,
    (pattern) => `category\tvalue\tvalid\n${pattern.rows.map(([key, value]) => `${key}\t${value}\tTRUE`).join('\n')}\n`,
    (pattern) => `<test-data title="${pattern.title}">\n${pattern.rows.map(([key, value]) => `  <item name="${key}">${value}</item>`).join('\n')}\n</test-data>\n`,
    (pattern) => `${pattern.title}\n${pattern.rows.map(([key, value], index) => `[${index % 2 ? ' ' : 'x'}] ${key} -- ${value}`).join('\n')}\n`,
  ];

  const makeText = (target, pattern, patternIndex) => {
    const seed = encoder.encode(textPatterns[patternIndex](pattern));
    const output = new Uint8Array(target);
    for (let offset = 0; offset < target; offset += seed.length) output.set(seed.subarray(0, Math.min(seed.length, target - offset)), offset);
    return output;
  };

  const pngChunk = (type, data) => {
    const typeBytes = ascii(type);
    return concat([be32(data.length), typeBytes, data, be32(crc32(concat([typeBytes, data])))]);
  };

  const drawPngPattern = (context, width, height, pattern, patternIndex) => {
    const accent = `#${pattern.accent}`;
    context.fillStyle = '#171a17';
    context.fillRect(0, 0, width, height);
    const block = Math.max(24, Math.floor(width / 16));
    if (patternIndex === 0) {
      for (let y = 0; y < height; y += block) for (let x = 0; x < width; x += block) {
        context.fillStyle = (Math.floor(x / block) + Math.floor(y / block)) % 2 ? accent : '#20241f';
        context.fillRect(x, y, block, block);
      }
    } else if (patternIndex === 1) {
      const gradient = context.createLinearGradient(0, 0, width, height);
      gradient.addColorStop(0, accent); gradient.addColorStop(1, '#111310');
      context.fillStyle = gradient; context.fillRect(0, 0, width, height);
    } else if (patternIndex === 2) {
      for (let x = -height; x < width; x += block * 2) {
        context.fillStyle = accent; context.fillRect(x, 0, block, height);
        context.save(); context.translate(x, 0); context.rotate(-Math.PI / 6); context.fillRect(0, 0, block, height * 2); context.restore();
      }
    } else if (patternIndex === 3) {
      for (let radius = Math.max(width, height); radius > 0; radius -= block) {
        context.beginPath(); context.arc(width / 2, height / 2, radius, 0, Math.PI * 2);
        context.fillStyle = Math.floor(radius / block) % 2 ? accent : '#20241f'; context.fill();
      }
    } else if (patternIndex === 4) {
      context.strokeStyle = accent; context.lineWidth = Math.max(3, width / 240);
      for (let x = 0; x <= width; x += block) { context.beginPath(); context.moveTo(x, 0); context.lineTo(x, height); context.stroke(); }
      for (let y = 0; y <= height; y += block) { context.beginPath(); context.moveTo(0, y); context.lineTo(width, y); context.stroke(); }
    } else if (patternIndex === 5) {
      for (let index = 0; index < 18; index += 1) {
        const radius = block * (.5 + (index % 4) * .3);
        context.beginPath(); context.arc((index * 97) % width, (index * 61) % height, radius, 0, Math.PI * 2);
        context.fillStyle = index % 2 ? accent : '#505650'; context.fill();
      }
    } else if (patternIndex === 6) {
      for (let y = 0; y < height; y += block) {
        context.fillStyle = Math.floor(y / block) % 2 ? accent : '#252925'; context.fillRect(0, y, width, block);
      }
    } else if (patternIndex === 7) {
      context.fillStyle = accent;
      context.beginPath(); context.moveTo(width / 2, height * .08); context.lineTo(width * .92, height * .88); context.lineTo(width * .08, height * .88); context.closePath(); context.fill();
      context.fillStyle = '#171a17'; context.beginPath(); context.moveTo(width / 2, height * .27); context.lineTo(width * .74, height * .75); context.lineTo(width * .26, height * .75); context.closePath(); context.fill();
    } else if (patternIndex === 8) {
      const gradient = context.createRadialGradient(width * .5, height * .5, block, width * .5, height * .5, width * .6);
      gradient.addColorStop(0, accent); gradient.addColorStop(.5, '#505650'); gradient.addColorStop(1, '#111310');
      context.fillStyle = gradient; context.fillRect(0, 0, width, height);
    } else {
      for (let y = 0; y < height; y += block) for (let x = 0; x < width; x += block) {
        context.fillStyle = ((x / block) ^ (y / block)) % 3 ? '#20241f' : accent; context.fillRect(x, y, block, block);
      }
    }
    context.fillStyle = 'rgba(12, 14, 12, .82)';
    context.fillRect(width * .055, height * .34, width * .89, height * .32);
  };

  const makePng = async (target, pattern, patternIndex) => {
    const [width, height] = dimension.value.split('x').map(Number);
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const context = canvas.getContext('2d');
    drawPngPattern(context, width, height, pattern, patternIndex);
    context.fillStyle = `#${pattern.accent}`;
    context.font = `700 ${Math.max(24, Math.floor(width / 15))}px system-ui`;
    context.fillText(pattern.title, Math.floor(width * .08), Math.floor(height * .48));
    context.fillStyle = '#f5f3eb';
    context.font = `500 ${Math.max(16, Math.floor(width / 32))}px system-ui`;
    context.fillText(`${pattern.name} · ${formatBytes(target)}`, Math.floor(width * .08), Math.floor(height * .58));
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
    if (!blob) throw new Error('PNG 이미지를 만들지 못했습니다.');
    const base = new Uint8Array(await blob.arrayBuffer());
    const remaining = target - base.length;
    if (remaining < 20) throw new Error(`선택한 용량이 이미지 최소 크기(${formatBytes(base.length + 20)})보다 작습니다.`);
    const padding = new Uint8Array(remaining - 12);
    padding.set(ascii('padding\0'));
    return concat([base.subarray(0, base.length - 12), pngChunk('tEXt', padding), base.subarray(base.length - 12)]);
  };

  const makeWebm = async (target) => {
    if (!window.MediaRecorder || !HTMLCanvasElement.prototype.captureStream) throw new Error('이 브라우저는 WebM 동영상 생성을 지원하지 않습니다. Chrome 또는 Edge를 사용해 주세요.');
    const canvas = document.createElement('canvas');
    canvas.width = 640;
    canvas.height = 360;
    const context = canvas.getContext('2d');
    const noise = context.createImageData(canvas.width, canvas.height);
    let noiseSeed = 0x12345678;
    const drawFrame = (frame) => {
      for (let index = 0; index < noise.data.length; index += 4) {
        noiseSeed = (noiseSeed * 1664525 + 1013904223) >>> 0;
        const shade = noiseSeed >>> 24;
        noise.data[index] = shade >> 2;
        noise.data[index + 1] = 40 + (shade >> 1);
        noise.data[index + 2] = 24 + (shade >> 3);
        noise.data[index + 3] = 255;
      }
      context.putImageData(noise, 0, 0);
      context.fillStyle = 'rgba(32, 36, 31, .82)'; context.fillRect(0, 0, canvas.width, canvas.height);
      context.fillStyle = '#d7ff5f'; context.fillRect(0, 0, canvas.width, 72);
      context.fillStyle = '#111310'; context.font = '700 36px system-ui'; context.fillText('ROSSI TEST VIDEO', 42, 47);
      context.fillStyle = '#f5f3eb'; context.font = '500 24px system-ui'; context.fillText(formatBytes(target), 42, 150);
      context.fillStyle = '#a6aca1'; context.font = '500 16px ui-monospace, monospace'; context.fillText(`FRAME ${String(frame).padStart(3, '0')}`, 42, 205);
      context.fillStyle = frame % 2 ? '#ff8c78' : '#d7ff5f'; context.fillRect(42 + (frame % 12) * 42, 252, 30, 30);
    };
    const stream = canvas.captureStream(12);
    const mimeType = ['video/webm;codecs=vp8', 'video/webm'].find((type) => MediaRecorder.isTypeSupported(type));
    if (!mimeType) throw new Error('이 브라우저에서 지원하는 WebM 코덱을 찾지 못했습니다. Chrome 또는 Edge를 사용해 주세요.');
    const durationSeconds = webmDurationForTarget(target);
    const options = { mimeType, videoBitsPerSecond: webmBitrate };
    const chunks = [];
    const recorder = new MediaRecorder(stream, options);
    const stopped = new Promise((resolve, reject) => { recorder.onstop = resolve; recorder.onerror = () => reject(new Error('WebM 동영상 생성에 실패했습니다.')); });
    recorder.ondataavailable = (event) => { if (event.data.size) chunks.push(event.data); };
    recorder.start(1000);
    let frame = 0;
    drawFrame(frame);
    const frameTimer = window.setInterval(() => drawFrame(++frame), 80);
    await new Promise((resolve) => window.setTimeout(resolve, durationSeconds * 1000));
    window.clearInterval(frameTimer);
    recorder.stop();
    await stopped;
    stream.getTracks().forEach((track) => track.stop());
    // Upload validators commonly accept the registered WebM MIME type only;
    // keep codec selection for recording but save the downloaded file as video/webm.
    return { blob: new Blob(chunks, { type: 'video/webm' }), durationSeconds };
  };

  const mp4SoundPatterns = ['440Hz 기준음', '880Hz 고음', '짧은 알림음', '긴 간격 비프음', '이중 화음', '상승 스윕', '교대 음계', '3음 아르페지오', '핑크 노이즈', '메트로놈'];

  const makeMp4 = async (target, patternIndex) => {
    const response = await fetch(`/static/test-video-${patternIndex + 1}.mp4?v=1`, { cache: 'no-cache' });
    if (!response.ok) throw new Error('MP4 원본 영상을 불러오지 못했습니다. 다시 시도해 주세요.');
    const template = new Uint8Array(await response.arrayBuffer());
    if (target < template.length + 8) throw new Error(`선택한 용량이 MP4 최소 크기(${formatBytes(template.length + 8)})보다 작습니다.`);
    const freeBoxSize = target - template.length;
    const header = concat([be32(freeBoxSize), ascii('free')]);
    const zeroChunk = new Uint8Array(Math.min(1024 * 1024, freeBoxSize - 8));
    const parts = [template, header];
    let remaining = freeBoxSize - 8;
    while (remaining > 0) {
      const length = Math.min(remaining, zeroChunk.length);
      parts.push(length === zeroChunk.length ? zeroChunk : zeroChunk.subarray(0, length));
      remaining -= length;
    }
    return { blob: new Blob(parts, { type: 'video/mp4' }), durationSeconds: 6, patternName: mp4SoundPatterns[patternIndex] };
  };

  const targetBytes = () => {
    const size = Number(fileSize.value);
    if (!Number.isFinite(size) || size < 1) throw new Error('용량은 1 이상으로 입력해 주세요.');
    const bytes = Math.round(size * (fileUnit.value === 'MiB' ? 1024 * 1024 : 1000 * 1000));
    if (bytes > maxBytes) throw new Error(`한 번에 최대 ${formatBytes(maxBytes)}까지만 만들 수 있습니다.`);
    return bytes;
  };

  const updateFileOptions = () => {
    const isImage = fileKind.value === 'png';
    const isVideo = fileKind.value === 'webm' || fileKind.value === 'mp4';
    imageOption.hidden = !isImage;
    fileSize.max = '300';
    fileHint.textContent = fileKind.value === 'mp4'
      ? 'H.264/AAC MP4로 생성하며 10가지 테스트 사운드 중 하나를 무작위로 넣습니다. MP4 표준 여유 영역으로 목표 바이트에 정확히 맞춥니다.'
      : isVideo
      ? '모바일 호환을 위해 실제 WebM을 녹화합니다. 목표 용량에 맞춰 영상 길이를 자동으로 정합니다. 실제 파일 크기는 목표에 가깝게 생성됩니다.'
      : 'PNG, TXT, PDF, DOCX, XLSX는 10가지 테스트 패턴 중 하나를 무작위로 만들고, 보이지 않는 패딩으로 목표 바이트에 맞춥니다.';
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
      setStatus(fileStatus, kind === 'webm'
        ? `WebM을 실제 녹화 중입니다… 예상 길이 ${formatDuration(webmDurationForTarget(target))}`
        : '파일을 생성하고 있습니다…');
      let data;
      let videoDurationSeconds;
      let generatedPatternName;
      const patternIndex = randomIndex(documentPatterns.length);
      const pattern = documentPatterns[patternIndex];
      if (kind === 'png') { data = await makePng(target, pattern, patternIndex); generatedPatternName = pattern.name; }
      else if (kind === 'txt') { data = makeText(target, pattern, patternIndex); generatedPatternName = pattern.name; }
      else if (kind === 'pdf') { data = makePdf(target, pattern, patternIndex); generatedPatternName = pattern.name; }
      else if (kind === 'docx') { data = makeDocx(target, pattern, patternIndex); generatedPatternName = pattern.name; }
      else if (kind === 'xlsx') { data = makeXlsx(target, pattern); generatedPatternName = pattern.name; }
      else if (kind === 'mp4') {
        const mp4 = await makeMp4(target, patternIndex);
        data = mp4.blob;
        videoDurationSeconds = mp4.durationSeconds;
        generatedPatternName = mp4.patternName;
      }
      else {
        const webm = await makeWebm(target);
        data = webm.blob;
        videoDurationSeconds = webm.durationSeconds;
      }
      const dataLength = data instanceof Blob ? data.size : data.length;
      if (kind !== 'webm' && dataLength !== target) throw new Error(`목표 용량과 다릅니다 (${dataLength.toLocaleString('ko-KR')} bytes).`);
      const mime = { png: 'image/png', txt: 'text/plain;charset=utf-8', pdf: 'application/pdf', docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', webm: 'video/webm', mp4: 'video/mp4' }[kind];
      download(data instanceof Blob ? data : new Blob([data], { type: mime }), `${safeName(fileName.value, 'test-file')}.${kind}`);
      setStatus(fileStatus, kind === 'webm'
        ? `WebM 영상 ${formatBytes(data.size)}을 만들었습니다. 자동 지정 길이: ${formatDuration(videoDurationSeconds)} · 목표: ${formatBytes(target)}`
        : kind === 'mp4'
          ? `H.264/AAC MP4 영상 ${formatBytes(data.size)}을 만들었습니다. 사운드: ${generatedPatternName} · 길이: ${formatDuration(videoDurationSeconds)} · 목표 용량과 일치합니다.`
        : `${kind.toUpperCase()} 파일 ${formatBytes(data.length)}을 만들었습니다. 패턴: ${generatedPatternName} (랜덤 10종)`);
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
