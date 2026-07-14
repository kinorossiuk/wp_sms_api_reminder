(() => {
  'use strict';

  const root = document.querySelector('[data-json-tool]');
  if (!root) return;

  const input = root.querySelector('#json-input');
  const output = root.querySelector('#json-output');
  const indent = root.querySelector('#json-indent');
  const wrap = root.querySelector('#json-wrap');
  const file = root.querySelector('#json-file');
  const formatButton = root.querySelector('#json-format');
  const treeButton = root.querySelector('#json-tree');
  const minifyButton = root.querySelector('#json-minify');
  const copyButton = root.querySelector('#json-copy');
  const downloadButton = root.querySelector('#json-download');
  const clearButton = root.querySelector('#json-clear');
  const exampleButton = root.querySelector('#json-example');
  const status = root.querySelector('#json-status');
  const modeLabel = root.querySelector('#json-mode');
  const encoder = new TextEncoder();
  let currentResult = '';
  let currentMode = 'pretty';

  const setStatus = (message, isError = false) => {
    status.textContent = message;
    status.classList.toggle('is-error', isError);
  };

  const setActions = (enabled) => {
    copyButton.disabled = !enabled;
    downloadButton.disabled = !enabled;
  };

  const indentation = () => indent.value === 'tab' ? '\t' : ' '.repeat(Number(indent.value));

  const validate = (source) => {
    if (!source.trim()) throw new Error('JSON 값을 입력해 주세요.');
    try {
      return JSON.parse(source);
    } catch (error) {
      const message = error instanceof Error ? error.message : '올바른 JSON이 아닙니다.';
      const position = message.match(/position\s+(\d+)/i);
      if (!position) throw new Error(`JSON 문법 오류: ${message}`);
      const offset = Number(position[1]);
      const before = source.slice(0, offset);
      const line = before.split('\n').length;
      const lastBreak = before.lastIndexOf('\n');
      const column = offset - lastBreak;
      throw new Error(`JSON 문법 오류 · ${line}행 ${column}열: ${message}`);
    }
  };

  const reformat = (source, compact = false) => {
    let result = '';
    let depth = 0;
    let inString = false;
    let escaped = false;
    const unit = indentation();
    const nextToken = (start) => {
      for (let index = start; index < source.length; index += 1) {
        if (!/\s/.test(source[index])) return source[index];
      }
      return '';
    };
    const newline = () => compact ? '' : `\n${unit.repeat(depth)}`;

    for (let index = 0; index < source.length; index += 1) {
      const character = source[index];
      if (inString) {
        result += character;
        if (escaped) escaped = false;
        else if (character === '\\') escaped = true;
        else if (character === '"') inString = false;
        continue;
      }
      if (character === '"') {
        inString = true;
        result += character;
      } else if (/\s/.test(character)) {
        continue;
      } else if (character === '{' || character === '[') {
        result += character;
        depth += 1;
        if (nextToken(index + 1) !== (character === '{' ? '}' : ']')) result += newline();
      } else if (character === '}' || character === ']') {
        depth = Math.max(0, depth - 1);
        const previous = result[result.length - 1];
        if (previous !== '{' && previous !== '[') result += newline();
        result += character;
      } else if (character === ',') {
        result += `,${newline()}`;
      } else if (character === ':') {
        result += compact ? ':' : ': ';
      } else {
        result += character;
      }
    }
    return result;
  };

  const highlight = (source) => {
    const code = document.createElement('code');
    const tokenPattern = /"(?:\\.|[^"\\])*"(?=\s*:)|"(?:\\.|[^"\\])*"|-?(?:0|[1-9]\d*)(?:\.\d+)?(?:[eE][+-]?\d+)?|\b(?:true|false|null)\b/g;
    let cursor = 0;
    for (const match of source.matchAll(tokenPattern)) {
      code.append(document.createTextNode(source.slice(cursor, match.index)));
      const token = match[0];
      const span = document.createElement('span');
      if (token.startsWith('"')) span.className = source.slice((match.index ?? 0) + token.length).trimStart().startsWith(':') ? 'json-key' : 'json-string';
      else if (token === 'true' || token === 'false') span.className = 'json-boolean';
      else if (token === 'null') span.className = 'json-null';
      else span.className = 'json-number';
      span.textContent = token;
      code.append(span);
      cursor = (match.index ?? 0) + token.length;
    }
    code.append(document.createTextNode(source.slice(cursor)));
    output.replaceChildren(code);
  };

  const rootSummary = (value) => {
    if (Array.isArray(value)) return `배열 ${value.length.toLocaleString('ko-KR')}개 항목`;
    if (value !== null && typeof value === 'object') return `객체 ${Object.keys(value).length.toLocaleString('ko-KR')}개 최상위 키`;
    if (value === null) return '최상위 값 null';
    return `최상위 값 ${typeof value}`;
  };

  const parseTree = (source) => {
    let index = 0;
    const skip = () => { while (/\s/.test(source[index] ?? '')) index += 1; };
    const readString = () => {
      const start = index;
      index += 1;
      let escaped = false;
      while (index < source.length) {
        const character = source[index++];
        if (escaped) escaped = false;
        else if (character === '\\') escaped = true;
        else if (character === '"') break;
      }
      const raw = source.slice(start, index);
      return { kind: 'string', raw, display: JSON.parse(raw) };
    };
    const readScalar = () => {
      const start = index;
      while (index < source.length && !/[\s,}\]]/.test(source[index])) index += 1;
      const raw = source.slice(start, index);
      return { kind: raw === 'true' || raw === 'false' ? 'boolean' : raw === 'null' ? 'null' : 'number', raw, display: raw };
    };
    const readValue = () => {
      skip();
      if (source[index] === '{') {
        index += 1;
        const children = [];
        skip();
        while (source[index] !== '}') {
          const key = readString().display;
          skip(); index += 1;
          children.push({ key, value: readValue() });
          skip();
          if (source[index] === ',') { index += 1; skip(); }
        }
        index += 1;
        return { kind: 'object', children };
      }
      if (source[index] === '[') {
        index += 1;
        const children = [];
        skip();
        while (source[index] !== ']') {
          children.push({ key: `[${children.length}]`, value: readValue() });
          skip();
          if (source[index] === ',') { index += 1; skip(); }
        }
        index += 1;
        return { kind: 'array', children };
      }
      return source[index] === '"' ? readString() : readScalar();
    };
    return readValue();
  };

  const renderTreeNode = (node, key, depth = 0) => {
    if (node.kind !== 'object' && node.kind !== 'array') {
      const row = document.createElement('div');
      row.className = 'json-tree-value';
      const keyElement = document.createElement('span');
      keyElement.className = 'json-tree-key';
      keyElement.textContent = key;
      const value = document.createElement('span');
      value.className = `json-tree-raw json-${node.kind}`;
      value.textContent = node.kind === 'string' ? node.display : node.raw;
      row.append(keyElement, value);
      return row;
    }
    const details = document.createElement('details');
    details.className = 'json-tree-node';
    details.open = depth < 2;
    const summary = document.createElement('summary');
    const keyElement = document.createElement('span');
    keyElement.className = 'json-tree-key';
    keyElement.textContent = key;
    const type = document.createElement('span');
    type.className = 'json-tree-type';
    type.textContent = node.kind === 'array' ? 'ARRAY' : 'OBJECT';
    const count = document.createElement('span');
    count.className = 'json-tree-count';
    count.textContent = `${node.children.length.toLocaleString('ko-KR')}개`;
    summary.append(keyElement, type, count);
    const children = document.createElement('div');
    children.className = 'json-tree-children';
    node.children.forEach((child) => children.append(renderTreeNode(child.value, child.key, depth + 1)));
    details.append(summary, children);
    return details;
  };

  const renderHuman = () => {
    try {
      const source = input.value;
      const parsed = validate(source);
      currentResult = reformat(source, false);
      currentMode = 'tree';
      modeLabel.textContent = 'HUMAN VIEW';
      const tree = parseTree(source);
      const container = document.createElement('div');
      container.className = 'json-tree-root';
      container.append(renderTreeNode(tree, tree.kind === 'array' ? 'ROOT ARRAY' : 'ROOT'));
      output.replaceChildren(container);
      output.classList.add('is-tree');
      setActions(true);
      setStatus(`${rootSummary(parsed)} · 구조 보기에서 객체와 배열을 눌러 펼치거나 접을 수 있습니다.`);
    } catch (error) {
      currentResult = '';
      output.classList.remove('is-tree');
      output.replaceChildren(Object.assign(document.createElement('code'), { textContent: 'JSON 문법을 확인해 주세요.' }));
      setActions(false);
      setStatus(error instanceof Error ? error.message : 'JSON을 처리하지 못했습니다.', true);
    }
  };

  const render = (compact) => {
    try {
      const source = input.value;
      const parsed = validate(source);
      currentResult = reformat(source, compact);
      currentMode = compact ? 'compact' : 'pretty';
      modeLabel.textContent = compact ? 'COMPACT' : 'PRETTY';
      output.classList.remove('is-tree');
      highlight(currentResult);
      setActions(true);
      const bytes = encoder.encode(currentResult).length;
      setStatus(`${rootSummary(parsed)} · ${currentResult.length.toLocaleString('ko-KR')}자 · UTF-8 ${bytes.toLocaleString('ko-KR')} bytes`);
    } catch (error) {
      currentResult = '';
      output.classList.remove('is-tree');
      output.replaceChildren(Object.assign(document.createElement('code'), { textContent: 'JSON 문법을 확인해 주세요.' }));
      setActions(false);
      setStatus(error instanceof Error ? error.message : 'JSON을 처리하지 못했습니다.', true);
    }
  };

  const download = () => {
    if (!currentResult) return;
    const url = URL.createObjectURL(new Blob([currentResult, '\n'], { type: 'application/json;charset=utf-8' }));
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = 'formatted.json';
    document.body.append(anchor);
    anchor.click();
    anchor.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
  };

  formatButton.addEventListener('click', () => render(false));
  treeButton.addEventListener('click', renderHuman);
  minifyButton.addEventListener('click', () => render(true));
  indent.addEventListener('change', () => { if (currentResult && currentMode === 'pretty') render(false); });
  wrap.addEventListener('change', () => output.classList.toggle('is-wrapped', wrap.checked));
  copyButton.addEventListener('click', async () => {
    if (!currentResult) return;
    try {
      await navigator.clipboard.writeText(currentResult);
      setStatus('정리된 JSON을 클립보드에 복사했습니다.');
    } catch (_) {
      const selection = window.getSelection();
      const range = document.createRange();
      range.selectNodeContents(output);
      selection.removeAllRanges();
      selection.addRange(range);
      document.execCommand('copy');
      selection.removeAllRanges();
      setStatus('정리된 JSON을 클립보드에 복사했습니다.');
    }
  });
  downloadButton.addEventListener('click', download);
  clearButton.addEventListener('click', () => {
    input.value = '';
    currentResult = '';
    output.classList.remove('is-tree');
    output.replaceChildren(Object.assign(document.createElement('code'), { textContent: 'JSON을 입력하고 정리 버튼을 눌러 주세요.' }));
    modeLabel.textContent = 'PRETTY';
    setActions(false);
    setStatus('입력값을 지웠습니다.');
    input.focus();
  });
  exampleButton.addEventListener('click', () => {
    input.value = '{"service":"ROSSI TOOLS","active":true,"limits":{"daily":20,"formats":["JSON","CSV","XLSX"]},"metadata":null,"largeId":900719925474099312345}';
    render(false);
  });
  file.addEventListener('change', async () => {
    const selected = file.files?.[0];
    if (!selected) return;
    try {
      input.value = await selected.text();
      render(false);
    } catch (_) {
      setStatus('JSON 파일을 읽지 못했습니다.', true);
    } finally {
      file.value = '';
    }
  });
  input.addEventListener('keydown', (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
      event.preventDefault();
      render(false);
    }
    if (event.key === 'Tab') {
      event.preventDefault();
      const start = input.selectionStart;
      input.setRangeText(indentation(), start, input.selectionEnd, 'end');
    }
  });
})();
